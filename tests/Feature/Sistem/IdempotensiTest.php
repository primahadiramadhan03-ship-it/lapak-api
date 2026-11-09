<?php

declare(strict_types=1);

use App\Balapan\IdempotensiGanda;
use App\Models\KunciIdempotensi;
use App\Models\Pengguna;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    Route::post('/api/_uji/idempoten', function () {
        return response()->json([
            'sukses' => true,
            'data' => [
                'pesan' => 'Operasi mutasi berhasil dieksekusi.',
                'acak' => Str::random(10),
            ],
            'meta' => null,
        ], 201);
    })->middleware(['api', 'auth:sesi', 'idempoten']);

    Route::post('/api/_uji/idempoten-wajib', function () {
        return response()->json(['sukses' => true, 'data' => ['ok' => true], 'meta' => null]);
    })->middleware(['api', 'auth:sesi', 'idempoten:wajib']);

    Route::post('/api/_uji/idempoten-galat', function () {
        abort(422, 'Gagal validasi bisnis.');
    })->middleware(['api', 'auth:sesi', 'idempoten']);

    $this->pengguna = Pengguna::factory()->create();
});

it('menyimpan catatan kunci idempotensi bersama respons sukses (LP-11)', function () {
    $kunci = 'KUNCI-'.Str::uuid();

    $respons = $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['jumlah' => 10])
        ->assertStatus(201)
        ->assertJsonPath('sukses', true);

    $this->assertDatabaseHas('kunci_idempotensi', [
        'pengguna_id' => $this->pengguna->id,
        'kunci' => $kunci,
        'metode' => 'POST',
        'jalur' => 'api/_uji/idempoten',
        'status_kode' => 201,
    ]);

    $catatan = KunciIdempotensi::query()
        ->where('pengguna_id', $this->pengguna->id)
        ->where('kunci', $kunci)
        ->first();

    expect($catatan)->not->toBeNull()
        ->and($catatan->respons)->toEqual($respons->json());
});

it('mengembalikan respons tersimpan bila kunci yang sama dikirimkan kembali (replay)', function () {
    $kunci = 'KUNCI-REPLAY-'.Str::uuid();

    $responsPertama = $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['produk_id' => 5])
        ->assertStatus(201);

    $tokenAcakPertama = $responsPertama->json('data.acak');

    // Permintaan kedua dengan kunci identik dan payload identik
    $responsKedua = $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['produk_id' => 5])
        ->assertStatus(201)
        ->assertHeader('X-Idempotent-Replayed', 'true');

    expect($responsKedua->json('data.acak'))->toBe($tokenAcakPertama);
});

it('menolak penggunaan kunci yang sama untuk payload berbeda dengan 409 (LP-11)', function () {
    $kunci = 'KUNCI-KONFLIK-'.Str::uuid();

    $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['produk_id' => 1])
        ->assertStatus(201);

    // Kunci sama, namun payload berbeda
    $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['produk_id' => 2])
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-11');
});

it('tidak menyimpan kunci bila permintaan downstream gagal (rollback atomik)', function () {
    $kunci = 'KUNCI-GAGAL-'.Str::uuid();

    $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten-galat', ['jumlah' => 10])
        ->assertStatus(422);

    $this->assertDatabaseMissing('kunci_idempotensi', [
        'kunci' => $kunci,
    ]);
});

it('menuntut header Idempotency-Key pada mode wajib (LP-11)', function () {
    $this->sebagai($this->pengguna)
        ->postJson('/api/_uji/idempoten-wajib', ['nominal' => 50000])
        ->assertStatus(400)
        ->assertJsonPath('galat.kode', 'LP-11');

    $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', 'KUNCI-OK')
        ->postJson('/api/_uji/idempoten-wajib', ['nominal' => 50000])
        ->assertOk();
});

it('memisahkan kunci idempotensi antar pengguna yang berbeda', function () {
    $penggunaLain = Pengguna::factory()->create();
    $kunci = 'KUNCI-BERSAMA';

    $res1 = $this->sebagai($this->pengguna)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['nominal' => 100])
        ->assertStatus(201);

    $res2 = $this->sebagai($penggunaLain)
        ->withHeader('Idempotency-Key', $kunci)
        ->postJson('/api/_uji/idempoten', ['nominal' => 100])
        ->assertStatus(201);

    // Masing-masing pengguna mendapatkan hasil mutasi sendiri
    expect($res1->json('data.acak'))->not->toBe($res2->json('data.acak'));

    expect(KunciIdempotensi::where('kunci', $kunci)->count())->toBe(2);
});

it('dapat menampilkan daftar skenario pada command lapak:balapan', function () {
    $this->artisan('lapak:balapan', ['--daftar' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('IdempotensiGanda');
});

it('memiliki skenario IdempotensiGanda yang mengevaluasi integritas dengan benar', function () {
    $skenario = app(IdempotensiGanda::class);
    $konteks = $skenario->siapkan();

    $hasilPembalap = [
        1 => ['pembalap' => 1, 'status' => 'created', 'kode' => 201],
        2 => ['pembalap' => 2, 'status' => 'conflict_or_blocked'],
        3 => ['pembalap' => 3, 'status' => 'conflict_or_blocked'],
        4 => ['pembalap' => 4, 'status' => 'replayed', 'kode' => 201],
        5 => ['pembalap' => 5, 'status' => 'conflict_or_blocked'],
    ];

    KunciIdempotensi::create([
        'pengguna_id' => $konteks['pengguna_id'],
        'kunci' => $konteks['kunci'],
        'metode' => $konteks['metode'],
        'jalur' => $konteks['jalur'],
        'hash_permintaan' => KunciIdempotensi::hitungHash($konteks['payload']),
        'status_kode' => 201,
        'respons' => ['sukses' => true],
    ]);

    $evaluasi = $skenario->evaluasi($hasilPembalap, $konteks);

    expect($evaluasi['valid'])->toBeTrue()
        ->and($evaluasi['sukses'])->toBe(2)
        ->and($evaluasi['gagal'])->toBe(3);
});

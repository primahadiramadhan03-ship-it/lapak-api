<?php

declare(strict_types=1);

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPencairan;
use App\Models\LogAudit;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Services\BukuBesar;
use Database\Seeders\DompetSeeder;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([PeranIzinSeeder::class, DompetSeeder::class]);

    $this->sri = Pengguna::factory()->penjual()->create(['nama' => 'Sri Penjual']);
    $this->toko = Toko::factory()->for($this->sri, 'pemilik')->create(['nama' => 'Batik Sri']);
    $this->buku = app(BukuBesar::class);

    // Beri saldo awal 363.500 ke Bu Sri
    $luar = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $dompetSri = $this->buku->dompetPenjualAtauBuat($this->sri);
    $this->buku->pindahkan($luar, $dompetSri, 363_500, JenisMutasi::LepasDana, 'SALDO-AWAL-SRI');

    $this->staf = Pengguna::factory()->keuangan()->create(['nama' => 'Staf Keuangan']);
    $this->auditor = Pengguna::factory()->auditor()->create(['nama' => 'Auditor']);

    $this->saldo = function (string $kode): int {
        return (int) DB::table('dompet')->where('kode', $kode)->value('saldo_rupiah');
    };

    $this->saldoSri = function (): int {
        return ($this->saldo)("penjual:{$this->sri->id}");
    };

    $this->ajukan = function (int $rupiah, string $rekening = 'BCA-1234567890', ?string $key = null) {
        $req = $this->sebagai($this->sri, segar: true);
        $key ??= 'IDEM-'.Str::random(10);

        return $req->withHeader('Idempotency-Key', $key)->postJson('/api/v1/dompet/pencairan', [
            'jumlah_rupiah' => $rupiah,
            'rekening_tujuan' => $rekening,
        ]);
    };

    $this->kirim = function (string $uri, array $payload = [], ?string $key = null) {
        $key ??= 'IDEM-'.Str::random(10);

        return $this->withHeader('Idempotency-Key', $key)->postJson("/api/v1/{$uri}", $payload);
    };
});

it('menolak pengajuan kurang dari batas minimal Rp 50.000 (LP-16)', function () {
    $this->sebagai($this->sri, segar: true);

    ($this->ajukan)(49_999)
        ->assertStatus(422)
        ->assertJsonPath('galat.kode', 'LP-16');

    expect(Pencairan::query()->count())->toBe(0)
        ->and(($this->saldoSri)())->toBe(363_500);
});

it('menolak jumlah yang melebihi saldo tanpa meninggalkan pengajuan', function () {
    $this->sebagai($this->sri);

    ($this->ajukan)(363_501)->assertConflict()->assertJsonPath('galat.kode', 'LP-13');

    expect(Pencairan::query()->count())->toBe(0)
        ->and(($this->saldoSri)())->toBe(363_500)
        ->and(LogAudit::query()->count())->toBe(0);
});

it('tidak dapat mengajukan uang yang sama dua kali', function () {
    $this->sebagai($this->sri);

    ($this->ajukan)(200_000)->assertCreated();
    // Saldo yang tersisa 163.500: pengajuan kedua sebesar 200.000 tidak punya sumbernya.
    ($this->ajukan)(200_000)->assertConflict()->assertJsonPath('galat.kode', 'LP-13');
    ($this->ajukan)(163_500)->assertCreated();

    expect(($this->saldoSri)())->toBe(0)->and(($this->saldo)('penahan'))->toBe(363_500);
});

it('berhasil mengajukan pencairan dan memindahkan saldo ke dompet penahan (LP-16)', function () {
    $this->sebagai($this->sri);

    $res = ($this->ajukan)(100_000, 'BCA-9876543210')->assertCreated();

    expect($res->json('data.status'))->toBe('menunggu')
        ->and($res->json('data.jumlah.rupiah'))->toBe(100_000)
        ->and(($this->saldoSri)())->toBe(263_500)
        ->and(($this->saldo)('penahan'))->toBe(100_000);

    $jejak = LogAudit::query()->where('peristiwa', 'pencairan.diajukan')->sole();
    expect($jejak->pelaku_id)->toBe($this->sri->id)
        ->and($jejak->sasaran)->toBe("pencairan:{$res->json('data.kode')}")
        ->and($jejak->rincian['jumlah_rupiah'])->toBe(100_000)
        ->and($jejak->rincian['pengaju_id'])->toBe($this->sri->id)
        ->and(isset($jejak->rincian['rekening_tujuan']))->toBeFalse();
});

it('menolak pengajuan pencairan dengan sesi tidak segar', function () {
    $this->sebagai($this->sri, segar: false)
        ->withHeader('Idempotency-Key', 'IDEM-SEGAR-TEST')
        ->postJson('/api/v1/dompet/pencairan', [
            'jumlah_rupiah' => 100_000,
            'rekening_tujuan' => 'BCA-12345',
        ])
        ->assertForbidden();
});

it('penjual dapat melihat daftar pengajuan pencairannya sendiri', function () {
    ($this->ajukan)(100_000);
    ($this->ajukan)(150_000);

    $this->sebagai($this->sri)
        ->getJson('/api/v1/dompet/pencairan')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('auditor tidak memegang izin pencairan.setujui (403)', function () {
    $this->sebagai($this->auditor, segar: true)
        ->getJson('/api/v1/keuangan/pencairan')
        ->assertForbidden();
});

it('staf keuangan dapat melihat daftar pengajuan pencairan dan menyaring berdasarkan status', function () {
    ($this->ajukan)(100_000);

    $this->sebagai($this->staf, segar: true)
        ->getJson('/api/v1/keuangan/pencairan?status=menunggu')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('menolak penyaringan status pencairan dengan nilai tidak sah', function () {
    $this->sebagai($this->staf, segar: true)
        ->getJson('/api/v1/keuangan/pencairan?status=tidak_valid')
        ->assertUnprocessable();
});

it('melarang pengaju memutuskan pencairannya sendiri (LP-16)', function () {
    // Bu Sri diangkat menjadi staf keuangan. Izinnya ada; aturannya tetap melarang.
    $sri = Pengguna::factory()->penjual()->keuangan()->create();
    $milikSri = Pencairan::factory()->create(['pengaju_id' => $sri->id]);

    $this->sebagai($sri, segar: true);
    ($this->kirim)("keuangan/pencairan/{$milikSri->kode}/setujui")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-16');

    expect($milikSri->refresh()->status)->toBe(StatusPencairan::Menunggu)
        ->and(LogAudit::query()->where('peristiwa', 'pencairan.disetujui')->count())->toBe(0);
});

it('menolak memutuskan pencairan yang sudah diputuskan (LP-16)', function () {
    $res = ($this->ajukan)(100_000);
    $kode = $res->json('data.kode');

    $this->sebagai($this->staf, segar: true);
    ($this->kirim)("keuangan/pencairan/{$kode}/setujui")->assertOk();

    // Keputusan kedua ditolak
    ($this->kirim)("keuangan/pencairan/{$kode}/tolak", ['catatan' => 'Ubah pikiran'])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-16');
});

it('staf keuangan menyetujui pencairan dan memindahkan dana dari penahan ke eksternal', function () {
    $res = ($this->ajukan)(100_000);
    $kode = $res->json('data.kode');

    $this->sebagai($this->staf, segar: true);
    ($this->kirim)("keuangan/pencairan/{$kode}/setujui")
        ->assertOk()
        ->assertJsonPath('data.status', 'disetujui');

    expect(($this->saldo)('penahan'))->toBe(0)
        ->and(($this->saldo)('eksternal'))->toBe(-263_500);

    $jejak = LogAudit::query()->where('peristiwa', 'pencairan.disetujui')->sole();
    expect($jejak->pelaku_id)->toBe($this->staf->id)
        ->and($jejak->sasaran)->toBe("pencairan:{$kode}");
});

it('staf keuangan menolak pencairan dan mengembalikan dana ke dompet penjual', function () {
    $res = ($this->ajukan)(100_000);
    $kode = $res->json('data.kode');

    $this->sebagai($this->staf, segar: true);
    ($this->kirim)("keuangan/pencairan/{$kode}/tolak", ['catatan' => 'Nomor rekening tidak valid'])
        ->assertOk()
        ->assertJsonPath('data.status', 'ditolak')
        ->assertJsonPath('data.catatan', 'Nomor rekening tidak valid');

    expect(($this->saldo)('penahan'))->toBe(0)
        ->and(($this->saldoSri)())->toBe(363_500);

    $jejak = LogAudit::query()->where('peristiwa', 'pencairan.ditolak')->sole();
    expect($jejak->pelaku_id)->toBe($this->staf->id)
        ->and($jejak->rincian['catatan'])->toBe('Nomor rekening tidak valid');
});

it('menolak kode pencairan yang tidak ditemukan', function () {
    $this->sebagai($this->staf, segar: true);
    ($this->kirim)('keuangan/pencairan/CAIR-TIDAK-ADA/setujui')->assertNotFound();
});

it('memastikan invarian keenam buku besar terjaga selama siklus pencairan (LP-16)', function () {
    $this->artisan('lapak:periksa-buku')->assertSuccessful();

    $this->sebagai($this->sri, segar: true);
    $respon = ($this->ajukan)(100_000)->assertCreated();

    $kode = $respon->json('data.kode');

    // Invarian keenam terpenuhi saat menunggu
    $this->artisan('lapak:periksa-buku')->assertSuccessful();

    // Setujui pencairan
    $this->sebagai($this->staf, segar: true);
    ($this->kirim)("keuangan/pencairan/{$kode}/setujui")->assertOk();

    // Invarian keenam tetap terpenuhi setelah disetujui
    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

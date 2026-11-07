<?php

declare(strict_types=1);

use App\Enums\NamaIzin;
use App\Enums\NamaPeran;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);

    $this->admin = Pengguna::factory()->admin()->create();
    $this->tini = Pengguna::factory()->pembeli()->create();
});

/**
 * Membentuk alamat pengelolaan peran.
 */
function alamatPeran(Pengguna $sasaran, string $peran): string
{
    return "/api/v1/admin/pengguna/{$sasaran->id}/peran/{$peran}";
}

it('menolak siapa pun yang tidak memegang izin peran.kelola', function (string $pemegang) {
    $pelaku = Pengguna::factory()->{$pemegang}()->create();

    $this->sebagai($pelaku)
        ->getJson('/api/v1/admin/pengguna')
        ->assertForbidden();

    $this->putJson(alamatPeran($this->tini, 'keuangan'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect($this->tini->punyaPeran(NamaPeran::Keuangan))->toBeFalse();
})->with([
    'pembeli',
    'penjual',
    'keuangan',
    'auditor',
]);

it('memerlukan autentikasi untuk membuka pintu admin', function () {
    $this->getJson('/api/v1/admin/pengguna')->assertUnauthorized();

    $this->putJson(alamatPeran($this->tini, 'keuangan'))
        ->assertUnauthorized();
});

it('menampilkan daftar pengguna dan dapat menyaring berdasarkan peran', function () {
    $respons = $this->sebagai($this->admin)
        ->getJson('/api/v1/admin/pengguna?peran=pembeli')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $this->tini->id)
        ->assertJsonPath('data.0.peran', ['pembeli']);

    expect($respons->json('data'))->toHaveCount(1);
});

it('menolak filter peran yang tidak dikenal', function () {
    $this->sebagai($this->admin)
        ->getJson('/api/v1/admin/pengguna?peran=raja')
        ->assertUnprocessable();
});

it('memberikan peran secara idempoten dan mencatat pemberinya', function () {
    $alamat = alamatPeran($this->tini, 'keuangan');

    $this->sebagai($this->admin)
        ->putJson($alamat)
        ->assertOk()
        ->assertJsonPath('data.peran', ['keuangan', 'pembeli']);

    $idPeran = Peran::query()
        ->where('nama', NamaPeran::Keuangan->value)
        ->value('id');

    $pivot = DB::table('pengguna_peran')
        ->where('pengguna_id', $this->tini->id)
        ->where('peran_id', $idPeran)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->diberikan_oleh)->toBe($this->admin->id);

    // Permintaan yang sama tidak membuat pivot kedua.
    $this->putJson($alamat)->assertOk();

    expect(
        DB::table('pengguna_peran')
            ->where('pengguna_id', $this->tini->id)
            ->where('peran_id', $idPeran)
            ->count()
    )->toBe(1);
});

it('mencabut peran dan izin yang dibawanya', function () {
    app(PengelolaPeran::class)->beri(
        $this->tini,
        NamaPeran::Keuangan,
        $this->admin,
    );

    $this->sebagai($this->admin)
        ->deleteJson(alamatPeran($this->tini, 'keuangan'))
        ->assertOk()
        ->assertJsonPath('data.peran', ['pembeli']);

    expect($this->tini->punyaPeran(NamaPeran::Keuangan))->toBeFalse()
        ->and(
            $this->tini->punyaIzin(NamaIzin::from('buku-besar.lihat'))
        )->toBeFalse();
});

it('mencabut seluruh sesi sasaran begitu perannya berubah', function () {
    [, $kunciTini] = app(PenerbitSesi::class)
        ->terbitkan($this->tini, 'Ponsel Tini');

    [$sesiAdmin, $kunciAdmin] = app(PenerbitSesi::class)
        ->terbitkan($this->admin, 'Laptop Admin');

    $this->denganKunci($kunciTini)
        ->getJson('/api/v1/saya')
        ->assertOk();

    $this->denganKunci($kunciAdmin)
        ->putJson(alamatPeran($this->tini, 'keuangan'))
        ->assertOk();

    // Seluruh sesi Tini dicabut.
    $this->denganKunci($kunciTini)
        ->getJson('/api/v1/saya')
        ->assertUnauthorized();

    // Sesi admin yang memberi peran tidak ikut dicabut.
    $this->denganKunci($kunciAdmin)
        ->getJson('/api/v1/saya')
        ->assertOk();

    expect($sesiAdmin->refresh()->dicabut_pada)->toBeNull();
});

it('menuntut sesi segar untuk mengubah peran, tetapi tidak untuk melihat daftar (LP-15)', function () {
    $this->sebagai($this->admin, segar: false);

    $this->getJson('/api/v1/admin/pengguna')->assertOk();

    $this->putJson(alamatPeran($this->tini, 'keuangan'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    $this->deleteJson(alamatPeran($this->tini, 'pembeli'))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    expect($this->tini->punyaPeran(NamaPeran::Pembeli))->toBeTrue();
});

it('melarang admin mengubah perannya sendiri', function (string $metode, string $peran) {
    $this->sebagai($this->admin)
        ->json($metode, alamatPeran($this->admin, $peran))
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect(app(PengelolaPeran::class)->daftar($this->admin))
        ->toBe(['admin']);
})->with([
    'menambah keuangan' => ['PUT', 'keuangan'],
    'melepas admin' => ['DELETE', 'admin'],
]);

it('membolehkan seorang admin mencabut admin lain', function () {
    $adminKedua = Pengguna::factory()->admin()->create();

    $this->sebagai($this->admin)
        ->deleteJson(alamatPeran($adminKedua, 'admin'))
        ->assertOk()
        ->assertJsonPath('data.peran', []);

    expect($adminKedua->punyaPeran(NamaPeran::Admin))->toBeFalse();
});

it('menolak peran penjual lewat pintu admin (LP-3)', function (string $metode) {
    $sri = Pengguna::factory()->penjual()->create();

    $this->sebagai($this->admin)
        ->json($metode, alamatPeran($sri, 'penjual'))
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-3');

    expect($sri->punyaPeran(NamaPeran::Penjual))->toBeTrue();
})->with([
    'memberi penjual' => ['PUT'],
    'mencabut penjual' => ['DELETE'],
]);

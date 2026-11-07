<?php

declare(strict_types=1);

use App\Enums\NamaPeran;
use App\Enums\StatusToko;
use App\Exceptions\TransisiTokoTidakSah;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\ModerasiToko;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->admin = Pengguna::factory()->admin()->create();
    $this->tini = Pengguna::factory()->pembeli()->create(['nama' => 'Tini Lestari']);
    $this->tokoTini = Toko::factory()->menunggu()->for($this->tini, 'pemilik')
        ->create(['nama' => 'Wedang Uwuh Bu Tini']);
});

it('menyetujui toko: status aktif, pemilik menjadi penjual, jejak tercatat', function () {
    Produk::factory()->for($this->tokoTini)->create();
    $this->tanpaKunci()->getJson('/api/v1/produk')->assertJsonCount(0, 'data');

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif')
        ->assertJsonPath('data.pemilik.id', $this->tini->id);

    $toko = $this->tokoTini->refresh();

    expect($toko->status)->toBe(StatusToko::Aktif)
        ->and($toko->dimoderasi_oleh)->toBe($this->admin->id)
        ->and($toko->dimoderasi_pada)->not->toBeNull()
        ->and(app(PengelolaPeran::class)->daftar($this->tini))->toBe(['pembeli', 'penjual']);

    // Produk yang sudah disiapkan langsung tampil di katalog publik (LP-4).
    $this->tanpaKunci()->getJson('/api/v1/produk')->assertJsonCount(1, 'data');
});

it('memaksa pemilik masuk ulang, dan baru setelah itu ia dapat mengelola produk', function () {
    [, $kunciLama] = app(PenerbitSesi::class)->terbitkan($this->tini, 'Ponsel Tini');
    $this->denganKunci($kunciLama)->getJson('/api/v1/toko-saya/produk')->assertForbidden();

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertOk();

    $this->denganKunci($kunciLama)->getJson('/api/v1/toko-saya/produk')->assertUnauthorized();
    $this->sebagai($this->tini)->getJson('/api/v1/toko-saya/produk')->assertOk();
});

it('menangguhkan toko dengan alasan, tanpa mencabut peran dan sesi pemiliknya', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($sri, 'pemilik')->create();
    Produk::factory()->for($toko)->create();
    [, $kunciSri] = app(PenerbitSesi::class)->terbitkan($sri, 'Laptop Sri');

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$toko->id}/tangguhkan", [
        'alasan' => 'Foto produk memakai merek pihak lain.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ditangguhkan')
        ->assertJsonPath('data.catatan_moderasi', 'Foto produk memakai merek pihak lain.');

    // Tertutup dari publik, tetapi pemilik masih masuk, masih penjual, dan melihat alasannya.
    $this->tanpaKunci()->getJson('/api/v1/produk')->assertJsonCount(0, 'data');
    $this->tanpaKunci()->getJson("/api/v1/toko/{$toko->slug}")->assertNotFound();
    $this->denganKunci($kunciSri)->getJson('/api/v1/toko-saya')
        ->assertOk()
        ->assertJsonPath('data.status', 'ditangguhkan')
        ->assertJsonPath('data.catatan_moderasi', 'Foto produk memakai merek pihak lain.')
        ->assertJsonMissingPath('data.dimoderasi_oleh');

    expect($sri->punyaPeran(NamaPeran::Penjual))->toBeTrue();
});

it('hanya mengubah satu kali bila dua admin menyetujui toko yang sama', function () {
    $adminKedua = Pengguna::factory()->admin()->create();

    // Kedua admin membuka halaman yang sama: masing-masing memegang salinan berstatus menunggu.
    $salinanPertama = Toko::query()->findOrFail($this->tokoTini->id);
    $salinanKedua = Toko::query()->findOrFail($this->tokoTini->id);

    app(ModerasiToko::class)->setujui($salinanPertama, $this->admin);

    // Salinan kedua masih berkata "menunggu", tetapi UPDATE bersyarat bertanya ke basis data.
    expect($salinanKedua->status)->toBe(StatusToko::Menunggu)
        ->and(fn () => app(ModerasiToko::class)->setujui($salinanKedua, $adminKedua))
        ->toThrow(TransisiTokoTidakSah::class)
        ->and($this->tokoTini->refresh()->dimoderasi_oleh)->toBe($this->admin->id);
});

it('melarang admin memoderasi tokonya sendiri', function () {
    $adminBerjualan = Pengguna::factory()->denganPeran(NamaPeran::Admin, NamaPeran::Pembeli)->create();
    $tokoSendiri = Toko::factory()->menunggu()->for($adminBerjualan, 'pemilik')->create();

    $this->sebagai($adminBerjualan)->postJson("/api/v1/admin/toko/{$tokoSendiri->id}/setujui")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    expect($tokoSendiri->refresh()->status)->toBe(StatusToko::Menunggu)
        ->and($adminBerjualan->punyaPeran(NamaPeran::Penjual))->toBeFalse();

    // Admin lain boleh.
    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$tokoSendiri->id}/setujui")->assertOk();
});

it('mendaftar seluruh toko untuk admin dengan pagination', function () {
    Toko::factory()->count(3)->create();

    $this->sebagai($this->admin)->getJson('/api/v1/admin/toko')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'nama', 'slug', 'kota', 'status', 'pemilik', 'jumlah_produk', 'catatan_moderasi', 'dimoderasi_pada'],
            ],
            'meta' => ['total', 'halaman', 'per_halaman'],
        ]);
});

it('dapat menyaring daftar toko berdasarkan status', function () {
    Toko::factory()->create(['status' => StatusToko::Aktif]);

    $this->sebagai($this->admin)->getJson('/api/v1/admin/toko?status=menunggu')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $this->tokoTini->id);
});

it('menolak akses moderasi tanpa izin toko.moderasi', function () {
    $pembeli = Pengguna::factory()->pembeli()->create();

    $this->sebagai($pembeli)->getJson('/api/v1/admin/toko')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');

    $this->sebagai($pembeli)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
});

it('menolak persetujuan dan penangguhan toko bila sesi tidak segar (LP-15)', function () {
    $this->sebagai($this->admin, segar: false)
        ->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    $this->sebagai($this->admin, segar: false)
        ->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/tangguhkan", ['alasan' => 'Pelanggaran ketentuan toko'])
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});

it('menolak persetujuan toko yang sudah aktif (LP-3)', function () {
    $tokoAktif = Toko::factory()->create(['status' => StatusToko::Aktif]);

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$tokoAktif->id}/setujui")
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-3');
});

it('menolak penangguhan toko yang masih menunggu persetujuan (LP-3)', function () {
    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/tangguhkan", [
        'alasan' => 'Belum memenuhi syarat verifikasi',
    ])
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-3');
});

it('menyetujui kembali toko yang sedang ditangguhkan', function () {
    $this->tokoTini->forceFill(['status' => StatusToko::Ditangguhkan])->save();

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertOk()
        ->assertJsonPath('data.status', 'aktif');

    expect($this->tokoTini->refresh()->status)->toBe(StatusToko::Aktif);
});

it('menolak penangguhan tanpa alasan atau alasan kurang dari 10 karakter', function (mixed $alasan) {
    $tokoAktif = Toko::factory()->create(['status' => StatusToko::Aktif]);

    $this->sebagai($this->admin)->postJson("/api/v1/admin/toko/{$tokoAktif->id}/tangguhkan", [
        'alasan' => $alasan,
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['galat' => ['rincian' => ['alasan']]]);
})->with([
    'kosong' => [''],
    'terlalu pendek' => ['pendek'],
]);

it('tidak mengubah status bila pemberian peran gagal', function () {
    DB::table('peran')->where('nama', NamaPeran::Penjual->value)->delete();

    $this->sebagai($this->admin)
        ->postJson("/api/v1/admin/toko/{$this->tokoTini->id}/setujui")
        ->assertStatus(500)
        ->assertJsonPath('galat.kode', 'GALAT_SERVER');

    expect($this->tokoTini->refresh()->status)->toBe(StatusToko::Menunggu);
});

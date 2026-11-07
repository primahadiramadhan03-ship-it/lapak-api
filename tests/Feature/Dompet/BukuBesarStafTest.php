<?php

declare(strict_types=1);

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Database\Seeders\DompetSeeder;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed([PeranIzinSeeder::class, DompetSeeder::class]);

    $this->sri = Pengguna::factory()->penjual()->create(['nama' => 'Sri Wahyuni']);
    $this->keuangan = Pengguna::factory()->keuangan()->create();

    // Rp 200.000 masuk escrow, lalu Rp 150.000 dilepas ke dompet Bu Sri.
    $buku = app(BukuBesar::class);
    $luar = $buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $buku->dompetSistem(JenisDompet::Escrow);

    $this->dompetSri = $buku->cariDompetPenjual($this->sri);
    if ($this->dompetSri === null) {
        $this->dompetSri = Dompet::buat('penjual:'.$this->sri->id, JenisDompet::Penjual, 0, $this->sri->id);
    }

    $buku->pindahkan($luar, $escrow, 200_000, JenisMutasi::DanaMasuk, 'LPK-UJI-3001');
    $buku->pindahkan(
        $escrow, $this->dompetSri, 150_000, JenisMutasi::LepasDana, 'LPK-UJI-3001',
    );
});

it('menolak akses tanpa kunci sesi', function () {
    $this->getJson('/api/v1/buku-besar/dompet')->assertUnauthorized();
    $this->getJson("/api/v1/buku-besar/dompet/{$this->dompetSri->id}/mutasi")->assertUnauthorized();
});

it('menolak akses tanpa sesi segar (LP-15)', function () {
    $this->sebagai($this->keuangan, segar: false)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');

    $this->sebagai($this->keuangan, segar: false)
        ->getJson("/api/v1/buku-besar/dompet/{$this->dompetSri->id}/mutasi")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});

it('membuka buku besar bagi keuangan dan auditor, dua peran dengan satu izin', function (
    string $pemegang,
) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create())
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.seimbang', true);
})->with(['keuangan', 'auditor']);

it('menutup buku besar bagi admin, penjual, dan pembeli', function (string $pemegang) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create());

    $this->getJson('/api/v1/buku-besar/dompet')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
    $this->getJson("/api/v1/buku-besar/dompet/{$this->dompetSri->id}/mutasi")->assertForbidden();
})->with(['admin', 'penjual', 'pembeli']);

it('tidak membuka dompet orang lain bagi penjual, ada maupun tidak ada nomornya', function () {
    $this->sebagai(Pengguna::factory()->penjual()->create());

    // Jawaban yang sama untuk nomor yang ada dan yang tidak ada: tidak ada yang dapat dipetakan.
    $this->getJson("/api/v1/buku-besar/dompet/{$this->dompetSri->id}/mutasi")->assertForbidden();
    $this->getJson('/api/v1/buku-besar/dompet/999999/mutasi')->assertForbidden();
});

it('menampilkan daftar seluruh dompet beserta pemilik dan saldo', function () {
    $respons = $this->sebagai($this->keuangan)
        ->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('meta.seimbang', true);

    $data = $respons->json('data');
    expect($data)->not->toBeEmpty();

    $dompetSri = collect($data)->firstWhere('id', $this->dompetSri->id);
    expect($dompetSri)->not->toBeNull()
        ->and($dompetSri['pemilik']['nama'])->toBe('Sri Wahyuni')
        ->and($dompetSri['saldo']['rupiah'])->toBe(150_000);
});

it('menampilkan mutasi sebuah dompet lengkap dengan kelompok dan nomor baris (LP-13)', function () {
    $respons = $this->sebagai($this->keuangan)
        ->getJson("/api/v1/buku-besar/dompet/{$this->dompetSri->id}/mutasi")
        ->assertOk()
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('meta.total', 1);

    $data = $respons->json('data');
    expect($data[0]['rujukan'])->toBe('LPK-UJI-3001')
        ->and($data[0]['kelompok'])->not->toBeNull()
        ->and($data[0]['id'])->toBeInt();
});

it('mengembalikan 404 bila dompet tidak ditemukan', function () {
    $this->sebagai($this->keuangan)
        ->getJson('/api/v1/buku-besar/dompet/999999/mutasi')
        ->assertNotFound();
});

it('melaporkan buku yang tidak seimbang apa adanya', function () {
    // Merusak saldo lewat jalan belakang, meniru UPDATE tangan di basis data.
    DB::table('dompet')->where('id', $this->dompetSri->id)->update(['saldo_rupiah' => 999_999]);

    $this->sebagai($this->keuangan)->getJson('/api/v1/buku-besar/dompet')
        ->assertOk()
        ->assertJsonPath('meta.seimbang', false)
        ->assertJsonPath('meta.jumlah_seluruh_saldo', 849_999);
});

it('tidak memiliki satu pun pintu yang menulis ke buku besar', function () {
    $menulis = collect(Route::getRoutes())
        ->filter(fn ($rute) => str_starts_with($rute->uri(), 'api/v1/buku-besar'))
        ->flatMap(fn ($rute) => array_diff($rute->methods(), ['GET', 'HEAD']));

    expect($menulis->all())->toBe([]);
});

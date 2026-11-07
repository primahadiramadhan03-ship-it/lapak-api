<?php

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\NamaPeran;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use App\Services\PengelolaPeran;
use Database\Seeders\PeranIzinSeeder;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);
});

/**
 * Mengisi saldo seorang penjual lewat jalur resmi: dana masuk escrow, lalu dilepas.
 */
function lepasDanaKe(Pengguna $penjual, int $rupiah, string $rujukan): void
{
    $buku = app(BukuBesar::class);

    // Pastikan dompet sistem ada (buat jika belum ada)
    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
    if ($luar === null) {
        $luar = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);
    }

    $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
    if ($escrow === null) {
        $escrow = Dompet::buat('escrow', JenisDompet::Escrow, 0);
    }

    // Cari dompet penjual, kalau belum ada baru buat
    $dompetPenjual = Dompet::query()->where('kode', 'penjual:'.$penjual->id)->first();
    if ($dompetPenjual === null) {
        $dompetPenjual = Dompet::buat('penjual:'.$penjual->id, JenisDompet::Penjual, 0);
    }

    $jumlah = Uang::dari($rupiah);
    $buku->pindahkan($luar, $escrow, $jumlah->rupiah, JenisMutasi::DanaMasuk, $rujukan);
    $buku->pindahkan($escrow, $dompetPenjual, $jumlah->rupiah, JenisMutasi::LepasDana, $rujukan);
}

it('menolak permintaan tanpa kunci sesi', function () {
    $this->getJson('/api/v1/dompet')->assertUnauthorized();
});

it('menampilkan saldo nol tanpa membuat dompet bagi penjual tanpa transaksi', function () {
    $this->sebagai(Pengguna::factory()->penjual()->create())
        ->getJson('/api/v1/dompet')
        ->assertOk()
        ->assertExactJson([
            'sukses' => true,
            'meta' => null,
            'data' => [
                'saldo' => ['rupiah' => 0, 'tampilan' => 'Rp 0'],
                'jumlah_mutasi' => 0,
            ],
        ]);

    expect(Dompet::count())->toBe(0);
});

it('menampilkan saldo milik sendiri dan bukan milik orang lain', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $wulan = Pengguna::factory()->penjual()->create();

    lepasDanaKe($sri, 363_500, 'LPK-UJI-1001');
    lepasDanaKe($wulan, 97_500, 'LPK-UJI-1002');

    $this->sebagai($sri)
        ->getJson('/api/v1/dompet')
        ->assertJsonPath('data.saldo.rupiah', 363_500)
        ->assertJsonPath('data.saldo.tampilan', 'Rp 363.500')
        ->assertJsonPath('data.jumlah_mutasi', 1);

    $this->sebagai($wulan)
        ->getJson('/api/v1/dompet')
        ->assertJsonPath('data.saldo.rupiah', 97_500);
});

it('menutup pintu dompet bagi yang tidak memegang izin dompet.lihat', function (string $pemegang) {
    $this->sebagai(Pengguna::factory()->{$pemegang}()->create());

    $this->getJson('/api/v1/dompet')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
    $this->getJson('/api/v1/dompet/mutasi')->assertForbidden();
})->with(['pembeli', 'admin', 'keuangan', 'auditor']);

it('menutup dompet begitu peran penjual dicabut', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $this->sebagai($sri)->getJson('/api/v1/dompet')->assertOk();

    app(PengelolaPeran::class)->cabut($sri, NamaPeran::Penjual);

    // Kunci lama mati bersama perannya. Setelah masuk ulang pun pintu dompet tertutup.
    $this->getJson('/api/v1/dompet')->assertUnauthorized();
    $this->sebagai($sri)->getJson('/api/v1/dompet')->assertForbidden();
});

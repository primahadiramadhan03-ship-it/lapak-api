<?php

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Services\BukuBesar;

it('menolak permintaan tanpa kunci sesi', function () {
    $this->getJson('/api/v1/dompet/mutasi')->assertUnauthorized();
});

it('menampilkan riwayat mutasi kosong untuk pengguna tanpa transaksi', function () {
    $this->sebagai(Pengguna::factory()->create())
        ->getJson('/api/v1/dompet/mutasi')
        ->assertOk()
        ->assertExactJson([
            'sukses' => true,
            'meta' => null,
            'data' => [],
        ]);
});

it('menampilkan riwayat mutasi milik sendiri dengan benar', function () {
    $sri = Pengguna::factory()->create();

    $buku = app(BukuBesar::class);

    // Pastikan dompet sistem ada
    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
    if ($luar === null) {
        $luar = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);
    }

    $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
    if ($escrow === null) {
        $escrow = Dompet::buat('escrow', JenisDompet::Escrow, 0);
    }

    // Pastikan dompet Sri ada
    $dompetSri = Dompet::query()->where('kode', 'penjual:'.$sri->id)->first();
    if ($dompetSri === null) {
        $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
    }

    // Lakukan 1x pemindahan dana ke Sri (ini akan menghasilkan 1 mutasi di dompet Sri)
    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-TEST-1');
    $buku->pindahkan($escrow, $dompetSri, 100000, JenisMutasi::LepasDana, 'TRX-TEST-1');

    $response = $this->sebagai($sri)
        ->getJson('/api/v1/dompet/mutasi');

    // Sri hanya punya 1 mutasi (yaitu dana masuk dari escrow)
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.jumlah_rupiah', 100000)
        ->assertJsonPath('data.0.saldo_setelah', 100000);
});

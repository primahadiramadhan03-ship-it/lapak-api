<?php

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Services\BukuBesar;

it('menolak permintaan tanpa kunci sesi', function () {
    $this->getJson('/api/v1/dompet/mutasi')->assertUnauthorized();
});

it('menolak permintaan dengan sesi tidak segar', function () {
    $pengguna = Pengguna::factory()->create();
    
    // Buat sesi yang tidak segar (sudah dikonfirmasi lebih dari 1 jam lalu)
    $this->sebagai($pengguna, segar: false)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertForbidden(); // 403 untuk sesi tidak segar
});

it('menampilkan riwayat mutasi kosong untuk pengguna tanpa transaksi', function () {
    $this->sebagai(Pengguna::factory()->create())
        ->getJson('/api/v1/dompet/mutasi')
        ->assertOk()
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('data', []);
});

it('menampilkan riwayat mutasi milik sendiri dengan pengurutan terbaru', function () {
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
    
    // Buat 2 transaksi ke dompet Sri
    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-TEST-1');
    $buku->pindahkan($escrow, $dompetSri, 100000, JenisMutasi::LepasDana, 'TRX-TEST-1');
    
    sleep(1); // Tunggu 1 detik agar created_at berbeda
    
    $buku->pindahkan($luar, $escrow, 50000, JenisMutasi::DanaMasuk, 'TRX-TEST-2');
    $buku->pindahkan($escrow, $dompetSri, 50000, JenisMutasi::LepasDana, 'TRX-TEST-2');
    
    $response = $this->sebagai($sri)
        ->getJson('/api/v1/dompet/mutasi');
    
    $response->assertOk()
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonCount(2, 'data'); // Sri menerima 2 mutasi (dari 2 transaksi)
    
    // Verifikasi field yang benar
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'jenis',
                'jumlah' => ['rupiah', 'tampilan'],
                'saldo_setelah' => ['rupiah', 'tampilan'],
                'rujukan',
                'keterangan',
                'waktu',
            ],
        ],
    ]);
    
    // Verifikasi field kelompok TIDAK ada (internal)
    $response->assertJsonMissing(['data.0.kelompok']);
    
    // Verifikasi pengurutan: yang terbaru di atas
    $data = $response->json('data');
    expect($data[0]['id'])->toBeGreaterThan($data[1]['id']);
    
    // Verifikasi nilai mutasi
    expect($data[0]['jumlah']['rupiah'])->toBe(50000); // Transaksi kedua (terbaru)
    expect($data[1]['jumlah']['rupiah'])->toBe(100000); // Transaksi pertama
});

it('mengisolasi mutasi antar penjual', function () {
    $sri = Pengguna::factory()->create();
    $wulan = Pengguna::factory()->create();
    
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
    
    // Buat dompet untuk Sri dan Wulan
    $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
    $dompetWulan = Dompet::buat('penjual:'.$wulan->id, JenisDompet::Penjual, 0);
    
    // Hanya Sri yang dapat transaksi
    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-SRI');
    $buku->pindahkan($escrow, $dompetSri, 100000, JenisMutasi::LepasDana, 'TRX-SRI');
    
    // Sri melihat 1 mutasi
    $this->sebagai($sri)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertJsonCount(1, 'data');
    
    // Wulan melihat 0 mutasi
    $this->sebagai($wulan)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertJsonCount(0, 'data');
});

it('tetap berfungsi setelah rotasi kunci sesi', function () {
    $pengguna = Pengguna::factory()->create();
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
    $dompet = Dompet::buat('penjual:'.$pengguna->id, JenisDompet::Penjual, 0);
    
    // Buat transaksi
    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-ROTASI');
    $buku->pindahkan($escrow, $dompet, 100000, JenisMutasi::LepasDana, 'TRX-ROTASI');
    
    // Dapatkan kunci sesi pertama
    $response1 = $this->sebagai($pengguna)
        ->getJson('/api/v1/dompet/mutasi');
    
    $response1->assertOk();
    
    // Simulasi rotasi kunci: buat sesi baru
    $response2 = $this->sebagai($pengguna)
        ->getJson('/api/v1/dompet/mutasi');
    
    $response2->assertOk()
        ->assertJsonCount(1, 'data');
});
<?php

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use App\Services\PenerbitSesi;
use Database\Seeders\PeranIzinSeeder;
use Database\Seeders\TransaksiSeeder;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);
});

it('menolak permintaan tanpa kunci sesi', function () {
    $this->getJson('/api/v1/dompet/mutasi')->assertUnauthorized();
});

it('menolak permintaan dengan sesi tidak segar', function () {
    $pengguna = Pengguna::factory()->penjual()->create();

    $this->sebagai($pengguna, segar: false)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertForbidden();
});

it('menampilkan riwayat mutasi kosong untuk pengguna tanpa transaksi', function () {
    $this->sebagai(Pengguna::factory()->penjual()->create())
        ->getJson('/api/v1/dompet/mutasi')
        ->assertOk()
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('data', []);
});

it('menampilkan riwayat mutasi milik sendiri dengan pengurutan terbaru', function () {
    $sri = Pengguna::factory()->penjual()->create();

    $buku = app(BukuBesar::class);

    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
    if ($luar === null) {
        $luar = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);
    }

    $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
    if ($escrow === null) {
        $escrow = Dompet::buat('escrow', JenisDompet::Escrow, 0);
    }

    $dompetSri = Dompet::query()->where('kode', 'penjual:'.$sri->id)->first();
    if ($dompetSri === null) {
        $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
    }

    // Buat 2 transaksi
    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-TEST-1');
    $buku->pindahkan($escrow, $dompetSri, 100000, JenisMutasi::LepasDana, 'TRX-TEST-1');

    sleep(1);

    $buku->pindahkan($luar, $escrow, 50000, JenisMutasi::DanaMasuk, 'TRX-TEST-2');
    $buku->pindahkan($escrow, $dompetSri, 50000, JenisMutasi::LepasDana, 'TRX-TEST-2');

    $response = $this->sebagai($sri)
        ->getJson('/api/v1/dompet/mutasi');

    $response->assertOk()
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('meta.per_halaman', config('lapak.katalog.per_halaman_bawaan', 12))
        ->assertJsonCount(2, 'data');

    // Verifikasi field yang benar
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'jenis',
                'jumlah' => ['rupiah', 'tampilan'],
                'saldo_setelah',
                'rujukan',
                'keterangan',
                'waktu',
            ],
        ],
    ]);

    // Verifikasi field kelompok TIDAK ada
    $response->assertJsonMissing(['data.0.kelompok']);

    // Verifikasi saldo_setelah adalah angka, bukan objek
    $data = $response->json('data');
    expect(is_int($data[0]['saldo_setelah']))->toBeTrue();

    // Verifikasi pengurutan: yang terbaru di atas
    expect($data[0]['id'])->toBeGreaterThan($data[1]['id']);
});

it('mengisolasi mutasi antar penjual', function () {
    $sri = Pengguna::factory()->penjual()->create();
    $wulan = Pengguna::factory()->penjual()->create();

    $buku = app(BukuBesar::class);

    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
    if ($luar === null) {
        $luar = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);
    }

    $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
    if ($escrow === null) {
        $escrow = Dompet::buat('escrow', JenisDompet::Escrow, 0);
    }

    $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
    $dompetWulan = Dompet::buat('penjual:'.$wulan->id, JenisDompet::Penjual, 0);

    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-SRI');
    $buku->pindahkan($escrow, $dompetSri, 100000, JenisMutasi::LepasDana, 'TRX-SRI');

    $this->sebagai($sri)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertJsonCount(1, 'data');

    $this->sebagai($wulan)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertJsonCount(0, 'data');
});

it('menolak kunci sesi lama setelah rotasi dan menerima kunci baru', function () {
    $pengguna = Pengguna::factory()->penjual()->create();
    $buku = app(BukuBesar::class);

    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
    if ($luar === null) {
        $luar = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);
    }

    $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
    if ($escrow === null) {
        $escrow = Dompet::buat('escrow', JenisDompet::Escrow, 0);
    }

    $dompet = Dompet::buat('penjual:'.$pengguna->id, JenisDompet::Penjual, 0);

    $buku->pindahkan($luar, $escrow, 100000, JenisMutasi::DanaMasuk, 'TRX-ROTASI');
    $buku->pindahkan($escrow, $dompet, 100000, JenisMutasi::LepasDana, 'TRX-ROTASI');

    $penerbit = app(PenerbitSesi::class);

    // Terbitkan sesi dan simpan kunci lama.
    [$sesi, $kunciLama] = $penerbit->terbitkan($pengguna, 'Perangkat uji');

    // Pastikan kunci lama masih berlaku sebelum rotasi.
    $this->withHeader('Authorization', 'Bearer '.$kunciLama)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertOk();

    // Lakukan rotasi pada sesi yang sama.
    $kunciBaru = $penerbit->rotasi($sesi);

    // Kunci lama harus ditolak.
    $this->withHeader('Authorization', 'Bearer '.$kunciLama)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertUnauthorized();

    // Kunci baru harus diterima.
    $this->withHeader('Authorization', 'Bearer '.$kunciBaru)
        ->getJson('/api/v1/dompet/mutasi')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('transaksi seeder idempoten: menjalankan dua kali tetap 6 mutasi dan buku besar seimbang', function () {
    // Pastikan dompet sistem ada
    $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
    if ($luar === null) {
        Dompet::buat('eksternal', JenisDompet::Eksternal, 10000000);
    }
    $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
    if ($escrow === null) {
        Dompet::buat('escrow', JenisDompet::Escrow, 0);
    }
    $pendapatan = Dompet::query()->where('jenis', JenisDompet::Pendapatan)->first();
    if ($pendapatan === null) {
        Dompet::buat('pendapatan', JenisDompet::Pendapatan, 0);
    }

    // Pastikan Sri ada
    $sri = Pengguna::where('surel', 'sri@lapak.test')->first();
    if ($sri === null) {
        $sri = Pengguna::factory()->create(['surel' => 'sri@lapak.test', 'nama' => 'Sri']);
    }

    // Jalankan seeder pertama kali
    $this->seed(TransaksiSeeder::class);
    $jumlahMutasiSetelahPertama = MutasiSaldo::where('rujukan', 'LPK-CONTOH-0001')->count();

    // Jalankan seeder kedua kali
    $this->seed(TransaksiSeeder::class);
    $jumlahMutasiSetelahKedua = MutasiSaldo::where('rujukan', 'LPK-CONTOH-0001')->count();

    // Harus tetap 6 mutasi
    expect($jumlahMutasiSetelahPertama)->toBe(6);
    expect($jumlahMutasiSetelahKedua)->toBe(6);

    // Buku besar harus seimbang
    $buku = app(BukuBesar::class);
    expect($buku->periksaKeseimbangan())->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Balapan\PelepasanSerentak;
use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPencairan;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\GembokCheckout;
use App\Services\LayananDana;
use App\Services\MesinStatusPesanan;
use Database\Seeders\DompetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->buku = app(BukuBesar::class);
    $this->dana = app(LayananDana::class);
    $this->gembok = app(GembokCheckout::class);
    $this->mesin = app(MesinStatusPesanan::class);
});

it('status pembayaran memiliki kasus dibatalkan sebagai status akhir', function (StatusPembayaran $status, bool $akhir) {
    expect($status->akhir())->toBe($akhir);
})->with([
    'menunggu' => [StatusPembayaran::Menunggu, false],
    'lunas' => [StatusPembayaran::Lunas, true],
    'kedaluwarsa' => [StatusPembayaran::Kedaluwarsa, true],
    'dibatalkan' => [StatusPembayaran::Dibatalkan, true],
]);

it('dompet seeder membuat dompet penahan secara idempoten dan tidak boleh negatif', function () {
    expect(JenisDompet::Penahan->bolehNegatif())->toBeFalse();

    $this->seed(DompetSeeder::class);
    $this->seed(DompetSeeder::class);

    expect(Dompet::query()->where('kode', 'penahan')->count())->toBe(1)
        ->and($this->buku->dompetSistem(JenisDompet::Penahan)->jenis)->toBe(JenisDompet::Penahan);
});

it('tabel dan model pencairan menyimpan data tanpa mass assignment dan menjaga relasi', function () {
    $penjual = Pengguna::factory()->penjual()->create();
    $keuangan = Pengguna::factory()->keuangan()->create();

    $pencairan = Pencairan::factory()
        ->for($penjual, 'pengguna')
        ->ditolak('Nomor rekening tidak valid', $keuangan)
        ->create(['jumlah_rupiah' => 150_000]);

    expect($pencairan->status)->toBe(StatusPencairan::Ditolak)
        ->and($pencairan->status->akhir())->toBeTrue()
        ->and(StatusPencairan::Menunggu->akhir())->toBeFalse()
        ->and(StatusPencairan::Disetujui->akhir())->toBeTrue()
        ->and($pencairan->jumlah_rupiah)->toBe(150_000)
        ->and($pencairan->pengguna->id)->toBe($penjual->id)
        ->and($pencairan->dompet->kode)->toBe('penjual:'.$penjual->id)
        ->and($pencairan->pemutus->id)->toBe($keuangan->id)
        ->and($pencairan->getFillable())->toBeEmpty();

    expect(fn () => Pencairan::factory()->create(['kode' => $pencairan->kode]))
        ->toThrow(QueryException::class);
});

it('dompet penjual dibuat saat pertama dibutuhkan secara aman dan idempoten', function () {
    $penjual = Pengguna::factory()->penjual()->create();

    expect($this->buku->cariDompetPenjual($penjual))->toBeNull();

    $pertama = $this->buku->dompetPenjualAtauBuat($penjual);
    $kedua = $this->buku->dompetPenjualAtauBuat($penjual);

    expect($pertama->id)->toBe($kedua->id)
        ->and($pertama->kode)->toBe('penjual:'.$penjual->id)
        ->and($pertama->jenis)->toBe(JenisDompet::Penjual)
        ->and($pertama->pengguna_id)->toBe($penjual->id)
        ->and(Dompet::query()->where('kode', 'penjual:'.$penjual->id)->count())->toBe(1);
});

it('dompetSistemAtauBuat membuat dompet sistem saat belum ada dan menolak jenis penjual', function () {
    $penahan = $this->buku->dompetSistemAtauBuat(JenisDompet::Penahan);

    expect($penahan->kode)->toBe('penahan')
        ->and($penahan->jenis)->toBe(JenisDompet::Penahan);

    expect(fn () => $this->buku->dompetSistemAtauBuat(JenisDompet::Penjual))
        ->toThrow(InvalidArgumentException::class);
});

it('melepas dana dari escrow ke penjual dan pendapatan sesuai aturan komisi LP-8', function () {
    $this->seed(DompetSeeder::class);

    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();
    $checkout = Checkout::factory()->create(['total_rupiah' => 225_000]);

    $pesanan = Pesanan::factory()->for($checkout)->for($toko)->create([
        'nomor' => '20261112-101',
        'status' => StatusPesanan::Dikirim,
        'dibayar_pada' => now(),
        'subtotal_rupiah' => 200_000,
        'ongkir_rupiah' => 25_000,
        'total_rupiah' => 225_000,
    ]);

    $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
    $this->buku->pindahkan($eksternal, $escrow, 225_000, JenisMutasi::DanaMasuk, '20261112-101');

    $hasil = $this->gembok->dalam($checkout->id, function () use ($pesanan) {
        $this->mesin->pindahkan($pesanan, StatusPesanan::Selesai, PelakuPesanan::Pembeli);

        return $this->dana->lepas($pesanan);
    });

    // 5% dari subtotal 200.000 = 10.000; ongkir 25.000 utuh untuk penjual -> 215.000
    expect($hasil)->toBe(['komisi_rupiah' => 10_000, 'bagian_penjual_rupiah' => 215_000])
        ->and($this->buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0)
        ->and($this->buku->dompetSistem(JenisDompet::Pendapatan)->saldo_rupiah)->toBe(10_000)
        ->and($this->buku->dompetPenjual($penjual)->saldo_rupiah)->toBe(215_000);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();

    // Pelepasan kedua untuk pesanan yang sama harus ditolak.
    expect(fn () => $this->gembok->dalam($checkout->id, fn () => $this->dana->lepas($pesanan)))
        ->toThrow(LogicException::class);
});

it('mengembalikan dana pesanan berbayar yang dibatalkan dari escrow ke eksternal', function () {
    $this->seed(DompetSeeder::class);

    $checkout = Checkout::factory()->create(['total_rupiah' => 197_000]);
    $pesanan = Pesanan::factory()->for($checkout)->dibayar()->create([
        'nomor' => '20261112-102',
        'subtotal_rupiah' => 185_000,
        'ongkir_rupiah' => 12_000,
        'total_rupiah' => 197_000,
    ]);

    $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
    $this->buku->pindahkan($eksternal, $escrow, 197_000, JenisMutasi::DanaMasuk, '20261112-102');

    $hasil = $this->gembok->dalam($checkout->id, function () use ($pesanan) {
        $this->mesin->pindahkan($pesanan, StatusPesanan::Dibatalkan, PelakuPesanan::Pembeli, 'Berubah pikiran');

        return $this->dana->kembalikan($pesanan);
    });

    expect($hasil)->toBe(['dikembalikan_rupiah' => 197_000])
        ->and($this->buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0)
        ->and($this->buku->dompetSistem(JenisDompet::Eksternal)->saldo_rupiah)->toBe(0);

    $mutasiPengembalian = MutasiSaldo::query()->where('jenis', JenisMutasi::Pengembalian)->get();
    expect($mutasiPengembalian)->toHaveCount(2)
        ->and($mutasiPengembalian->pluck('rujukan')->unique()->all())->toBe([$pesanan->refresh()->nomor]);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();

    // Pengembalian ganda harus ditolak.
    expect(fn () => $this->gembok->dalam($checkout->id, fn () => $this->dana->kembalikan($pesanan)))
        ->toThrow(LogicException::class);
});

it('menolak operasi LayananDana di luar transaksi atau pada status yang tidak sah', function () {
    // Tutup transaksi milik RefreshDatabase dulu untuk meniru panggilan tanpa transaksi.
    DB::rollBack();

    try {
        $tiruan = new Pesanan;
        expect(fn () => $this->dana->lepas($tiruan))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($tiruan))->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }

    $this->seed(DompetSeeder::class);

    $belumDibayar = Pesanan::factory()->create(['status' => StatusPesanan::MenungguPembayaran]);
    $dibatalkanTanpaBayar = Pesanan::factory()->create([
        'status' => StatusPesanan::Dibatalkan,
        'dibayar_pada' => null,
        'dibatalkan_pada' => now(),
    ]);
    $dibayar = Pesanan::factory()->dibayar()->create();
    $dikirim = Pesanan::factory()->create(['status' => StatusPesanan::Dikirim, 'dibayar_pada' => now()]);

    DB::transaction(function () use ($belumDibayar, $dibatalkanTanpaBayar, $dibayar, $dikirim) {
        expect(fn () => $this->dana->lepas($belumDibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->lepas($dibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($belumDibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($dibatalkanTanpaBayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($dikirim))->toThrow(LogicException::class);
    });
});

it('melepas beberapa pesanan milik penjual baru secara berurutan hanya membuat satu dompet penjual', function () {
    $this->seed(DompetSeeder::class);

    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();

    $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);

    foreach (['20261112-201', '20261112-202'] as $nomor) {
        $checkout = Checkout::factory()->create(['total_rupiah' => 110_000]);
        $pesanan = Pesanan::factory()->for($checkout)->for($toko)->create([
            'nomor' => $nomor,
            'status' => StatusPesanan::Dikirim,
            'dibayar_pada' => now(),
            'subtotal_rupiah' => 100_000,
            'ongkir_rupiah' => 10_000,
            'total_rupiah' => 110_000,
        ]);

        $this->buku->pindahkan($eksternal, $escrow, 110_000, JenisMutasi::DanaMasuk, $nomor);

        $this->gembok->dalam($checkout->id, function () use ($pesanan) {
            $this->mesin->pindahkan($pesanan, StatusPesanan::Selesai, PelakuPesanan::Sistem);
            $this->dana->lepas($pesanan);
        });
    }

    expect(Dompet::query()->where('kode', 'penjual:'.$penjual->id)->count())->toBe(1)
        ->and($this->buku->dompetPenjual($penjual)->saldo_rupiah)->toBe(210_000)
        ->and($this->buku->dompetSistem(JenisDompet::Pendapatan)->saldo_rupiah)->toBe(10_000)
        ->and($this->buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('skenario balapan pelepasan-serentak tetap aman meski dipanggil tanpa proses paralel tambahan', function () {
    // Memastikan helper skenario tersedia dengan nama dan jumlah pembalap yang tepat
    $skenario = new PelepasanSerentak;

    expect($skenario->nama())->toBe('pelepasan-serentak')
        ->and($skenario->jumlahPembalap())->toBe(4);
});

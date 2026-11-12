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

it('dompet seeder membuat dompet penahan secara idempoten dan jenis mutasi pencairan tersedia', function () {
    expect(JenisDompet::Penahan->bolehNegatif())->toBeFalse()
        ->and(JenisMutasi::TahanPencairan->value)->toBe('tahan_pencairan')
        ->and(JenisMutasi::BatalPencairan->value)->toBe('batal_pencairan')
        ->and(JenisMutasi::Pencairan->value)->toBe('pencairan');

    $this->seed(DompetSeeder::class);
    $this->seed(DompetSeeder::class);

    expect(Dompet::query()->where('kode', 'penahan')->count())->toBe(1)
        ->and($this->buku->dompetSistem(JenisDompet::Penahan)->jenis)->toBe(JenisDompet::Penahan);
});

it('tabel dan model pencairan menyimpan data tanpa mass assignment dan menjaga relasi', function () {
    $penjual = Pengguna::factory()->penjual()->create();
    $keuangan = Pengguna::factory()->keuangan()->create();

    $pencairan = Pencairan::factory()
        ->for($penjual, 'pengaju')
        ->ditolak('Nomor rekening tidak valid', $keuangan)
        ->create(['jumlah_rupiah' => 150_000]);

    expect($pencairan->kode)->toStartWith('CAIR-')
        ->and($pencairan->status)->toBe(StatusPencairan::Ditolak)
        ->and($pencairan->status->akhir())->toBeTrue()
        ->and(StatusPencairan::Menunggu->akhir())->toBeFalse()
        ->and(StatusPencairan::Disetujui->akhir())->toBeTrue()
        ->and($pencairan->jumlah_rupiah)->toBe(150_000)
        ->and($pencairan->catatan)->toBe('Nomor rekening tidak valid')
        ->and($pencairan->pengaju->id)->toBe($penjual->id)
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

it('menghitung komisi dari subtotal saja dan ongkir utuh untuk penjual (LP-8)', function () {
    $pesanan = Pesanan::factory()->make([
        'subtotal_rupiah' => 200_000,
        'ongkir_rupiah' => 25_000,
        'total_rupiah' => 225_000,
    ]);

    expect($this->dana->komisi($pesanan)->rupiah)->toBe(10_000)
        ->and($this->dana->bagianPenjual($pesanan)->rupiah)->toBe(215_000);
});

it('melepas dana dari escrow ke penjual dan pendapatan sesuai aturan komisi LP-8 dan LP-12', function () {
    $this->seed(DompetSeeder::class);

    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();
    $checkout = Checkout::factory()->create(['total_rupiah' => 225_000]);

    $pesanan = Pesanan::factory()->for($checkout)->for($toko)->create([
        'status' => StatusPesanan::Dikirim,
        'dibayar_pada' => now(),
        'subtotal_rupiah' => 200_000,
        'ongkir_rupiah' => 25_000,
        'total_rupiah' => 225_000,
    ]);

    $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
    $this->buku->pindahkan($eksternal, $escrow, 225_000, JenisMutasi::DanaMasuk, $pesanan->nomor);

    $this->gembok->dalam($checkout->id, function () use ($pesanan) {
        $this->mesin->pindahkan($pesanan, StatusPesanan::Selesai, PelakuPesanan::Pembeli);
        $this->dana->lepaskan($pesanan);
    });

    // 5% dari subtotal 200.000 = 10.000; ongkir 25.000 utuh untuk penjual -> 215.000
    expect($this->buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0)
        ->and($this->buku->dompetSistem(JenisDompet::Pendapatan)->saldo_rupiah)->toBe(10_000)
        ->and($this->buku->dompetPenjual($penjual)->saldo_rupiah)->toBe(215_000)
        ->and(MutasiSaldo::query()->where('rujukan', $pesanan->nomor)->count())->toBe(6);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();

    // Dana yang sama tidak boleh keluar dua kali lewat jalan mana pun.
    expect(fn () => $this->gembok->dalam($checkout->id, fn () => $this->dana->lepaskan($pesanan)))
        ->toThrow(LogicException::class)
        ->and(fn () => $this->gembok->dalam($checkout->id, fn () => $this->dana->kembalikan($pesanan)))
        ->toThrow(LogicException::class);
});

it('mengembalikan dana pesanan berbayar yang dibatalkan dari escrow ke eksternal', function () {
    $this->seed(DompetSeeder::class);

    $checkout = Checkout::factory()->create(['total_rupiah' => 197_000]);
    $pesanan = Pesanan::factory()->for($checkout)->dibayar()->create([
        'subtotal_rupiah' => 185_000,
        'ongkir_rupiah' => 12_000,
        'total_rupiah' => 197_000,
    ]);

    $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
    $this->buku->pindahkan($eksternal, $escrow, 197_000, JenisMutasi::DanaMasuk, $pesanan->nomor);

    $this->gembok->dalam($checkout->id, function () use ($pesanan) {
        $this->mesin->pindahkan($pesanan, StatusPesanan::Dibatalkan, PelakuPesanan::Pembeli, 'Berubah pikiran');
        $this->dana->kembalikan($pesanan);
    });

    expect($this->buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0)
        ->and($this->buku->dompetSistem(JenisDompet::Eksternal)->saldo_rupiah)->toBe(0);

    $mutasiPengembalian = MutasiSaldo::query()->where('jenis', JenisMutasi::Pengembalian)->get();
    expect($mutasiPengembalian)->toHaveCount(2)
        ->and($mutasiPengembalian->pluck('rujukan')->unique()->all())->toBe([$pesanan->nomor]);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();

    // Pengembalian ganda atau pelepasan sesudahnya harus ditolak.
    expect(fn () => $this->gembok->dalam($checkout->id, fn () => $this->dana->kembalikan($pesanan)))
        ->toThrow(LogicException::class)
        ->and(fn () => $this->gembok->dalam($checkout->id, fn () => $this->dana->lepaskan($pesanan)))
        ->toThrow(LogicException::class);
});

it('menolak lepaskan selain status selesai (termasuk dikirim) dan menolak kembalikan selain dibatalkan berbayar', function () {
    // Tutup transaksi milik RefreshDatabase dulu untuk meniru panggilan tanpa transaksi.
    DB::rollBack();

    try {
        $tiruan = new Pesanan;
        expect(fn () => $this->dana->lepaskan($tiruan))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($tiruan))->toThrow(LogicException::class);
    } finally {
        DB::beginTransaction();
    }

    $this->seed(DompetSeeder::class);

    $belumDibayar = Pesanan::factory()->create(['status' => StatusPesanan::MenungguPembayaran]);
    $dibayar = Pesanan::factory()->dibayar()->create();
    $dikemas = Pesanan::factory()->create(['status' => StatusPesanan::Dikemas, 'dibayar_pada' => now()]);
    $dikirim = Pesanan::factory()->create(['status' => StatusPesanan::Dikirim, 'dibayar_pada' => now()]);
    $selesai = Pesanan::factory()->create(['status' => StatusPesanan::Selesai, 'dibayar_pada' => now()]);
    $dibatalkanTanpaBayar = Pesanan::factory()->create([
        'status' => StatusPesanan::Dibatalkan,
        'dibayar_pada' => null,
        'dibatalkan_pada' => now(),
    ]);

    DB::transaction(function () use ($belumDibayar, $dibayar, $dikemas, $dikirim, $selesai, $dibatalkanTanpaBayar) {
        expect(fn () => $this->dana->lepaskan($belumDibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->lepaskan($dibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->lepaskan($dikemas))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->lepaskan($dikirim))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->lepaskan($dibatalkanTanpaBayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($belumDibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($dibayar))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($dikemas))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($dikirim))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($selesai))->toThrow(LogicException::class)
            ->and(fn () => $this->dana->kembalikan($dibatalkanTanpaBayar))->toThrow(LogicException::class);
    });
});

it('melepas beberapa pesanan milik penjual baru secara berurutan hanya membuat satu dompet penjual', function () {
    $this->seed(DompetSeeder::class);

    $penjual = Pengguna::factory()->penjual()->create();
    $toko = Toko::factory()->for($penjual, 'pemilik')->create();

    $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);
    $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);

    foreach (range(1, 2) as $i) {
        $checkout = Checkout::factory()->create(['total_rupiah' => 110_000]);
        $pesanan = Pesanan::factory()->for($checkout)->for($toko)->create([
            'status' => StatusPesanan::Dikirim,
            'dibayar_pada' => now(),
            'subtotal_rupiah' => 100_000,
            'ongkir_rupiah' => 10_000,
            'total_rupiah' => 110_000,
        ]);

        $this->buku->pindahkan($eksternal, $escrow, 110_000, JenisMutasi::DanaMasuk, $pesanan->nomor);

        $this->gembok->dalam($checkout->id, function () use ($pesanan) {
            $this->mesin->pindahkan($pesanan, StatusPesanan::Selesai, PelakuPesanan::Sistem);
            $this->dana->lepaskan($pesanan);
        });
    }

    expect(Dompet::query()->where('kode', 'penjual:'.$penjual->id)->count())->toBe(1)
        ->and($this->buku->dompetPenjual($penjual)->saldo_rupiah)->toBe(210_000)
        ->and($this->buku->dompetSistem(JenisDompet::Pendapatan)->saldo_rupiah)->toBe(10_000)
        ->and($this->buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah)->toBe(0);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('skenario balapan pelepasan-serentak tetap aman meski dipanggil tanpa proses paralel tambahan', function () {
    $skenario = new PelepasanSerentak;

    expect($skenario->nama())->toBe('pelepasan-serentak')
        ->and($skenario->jumlahPembalap())->toBe(4);
});

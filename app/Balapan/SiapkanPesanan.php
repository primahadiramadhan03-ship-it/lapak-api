<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPembayaran;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;
use Database\Seeders\DompetSeeder;

trait SiapkanPesanan
{
    public const HARGA = 185_000;

    public const ONGKIR = 12_000;

    public const SUBTOTAL = 370_000;

    public const TOTAL = 382_000;

    public const KOMISI = 18_500;

    public const BAGIAN_PENJUAL = 363_500;

    protected function saldoSistem(JenisDompet $jenis): int
    {
        return app(BukuBesar::class)->dompetSistem($jenis)->saldo_rupiah;
    }

    protected function siapkanPesanan(
        Pengguna $pembeli,
        Toko $toko,
        Produk $produk,
        int $kuantitas = 2,
    ): Pesanan {
        app(DompetSeeder::class)->run();

        $subtotal = self::HARGA * $kuantitas;
        $total = $subtotal + self::ONGKIR;

        $checkout = Checkout::factory()->for($pembeli, 'pembeli')->create([
            'total_rupiah' => $total,
        ]);

        Pembayaran::factory()->for($checkout)->create([
            'jumlah_rupiah' => $total,
            'status' => StatusPembayaran::Lunas,
            'batas_bayar_pada' => now()->subMinute(),
            'lunas_pada' => now(),
        ]);

        $pesanan = Pesanan::factory()->for($checkout)->create([
            'pembeli_id' => $pembeli->id,
            'toko_id' => $toko->id,
            'subtotal_rupiah' => $subtotal,
            'ongkir_rupiah' => self::ONGKIR,
            'total_rupiah' => $total,
            'status' => StatusPesanan::Dibayar,
            'dibayar_pada' => now(),
        ]);

        ButirPesanan::factory()->for($pesanan)->create([
            'produk_id' => $produk->id,
            'nama_produk' => $produk->nama,
            'harga_rupiah' => self::HARGA,
            'kuantitas' => $kuantitas,
            'subtotal_rupiah' => $subtotal,
        ]);

        $buku = app(BukuBesar::class);
        $buku->pindahkan(
            $buku->dompetSistem(JenisDompet::Eksternal),
            $buku->dompetSistem(JenisDompet::Escrow),
            $total,
            JenisMutasi::DanaMasuk,
            $pesanan->nomor,
            'Simulasi escrow balapan',
        );

        return $pesanan;
    }
}

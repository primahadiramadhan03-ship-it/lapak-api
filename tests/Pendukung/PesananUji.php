<?php

declare(strict_types=1);

namespace Tests\Pendukung;

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
use App\Services\BukuBesar;
use Database\Seeders\DompetSeeder;

/**
 * Keadaan awal bersama untuk uji Sesi 5: sebuah pesanan pada tahap tertentu, lengkap dengan
 * checkout, tagihan, satu butir, dan uangnya di tempat yang benar.
 *
 * Angkanya tetap agar mudah dihitung di kepala: harga satuan Rp 185.000 dan ongkir Rp 12.000.
 * Dua buah berarti subtotal 370.000, total 382.000, komisi 18.500, bagian penjual 363.500.
 *
 * Stok produk TIDAK dikurangi di sini. Uji yang peduli stok mengaturnya pada produknya.
 */
final class PesananUji
{
    public const HARGA = 185_000;

    public const ONGKIR = 12_000;

    /** Baru checkout: tagihan menunggu, belum ada uang yang masuk. */
    public static function belumDibayar(
        Pengguna $pembeli,
        Produk $produk,
        int $kuantitas = 2,
    ): Pesanan {
        return self::buat($pembeli, $produk, $kuantitas, StatusPembayaran::Menunggu);
    }

    /** Tagihan lunas dan seluruh nilainya sudah berada di escrow. */
    public static function dibayar(Pengguna $pembeli, Produk $produk, int $kuantitas = 2): Pesanan
    {
        $pesanan = self::buat($pembeli, $produk, $kuantitas, StatusPembayaran::Lunas);
        $pesanan->forceFill(['status' => StatusPesanan::Dibayar, 'dibayar_pada' => now()])->save();

        $buku = app(BukuBesar::class);
        $buku->pindahkan(
            $buku->dompetSistem(JenisDompet::Eksternal),
            $buku->dompetSistem(JenisDompet::Escrow),
            $pesanan->total_rupiah,
            JenisMutasi::DanaMasuk,
            $pesanan->nomor,
        );

        return $pesanan;
    }

    private static function buat(
        Pengguna $pembeli,
        Produk $produk,
        int $kuantitas,
        StatusPembayaran $statusPembayaran,
    ): Pesanan {
        (new DompetSeeder)->run();

        $subtotal = self::HARGA * $kuantitas;
        $total = $subtotal + self::ONGKIR;

        $checkout = Checkout::factory()->for($pembeli, 'pembeli')->create([
            'total_rupiah' => $total,
        ]);

        Pembayaran::factory()->for($checkout)->create([
            'jumlah_rupiah' => $total,
            'status' => $statusPembayaran,
            'batas_bayar_pada' => $statusPembayaran === StatusPembayaran::Menunggu ? now()->addDay() : now()->subMinute(),
            'lunas_pada' => $statusPembayaran === StatusPembayaran::Lunas ? now() : null,
        ]);

        $pesanan = Pesanan::factory()->for($checkout)->create([
            'pembeli_id' => $pembeli->id,
            'toko_id' => $produk->toko_id,
            'subtotal_rupiah' => $subtotal,
            'ongkir_rupiah' => self::ONGKIR,
            'total_rupiah' => $total,
        ]);

        ButirPesanan::factory()->for($pesanan)->create([
            'produk_id' => $produk->id,
            'nama_produk' => $produk->nama,
            'harga_rupiah' => self::HARGA,
            'kuantitas' => $kuantitas,
            'subtotal_rupiah' => $subtotal,
        ]);

        return $pesanan;
    }
}

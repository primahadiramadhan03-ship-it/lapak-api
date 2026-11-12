<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Models\Pembayaran;

/**
 * LP-10: checkout yang tidak dibayar sampai batas waktunya dibatalkan dan stoknya kembali.
 *
 * Tanpa kelas ini, siapa pun dapat mengosongkan stok seluruh toko tanpa membayar sepeser pun:
 * cukup checkout, lalu tidak pernah melunasinya.
 *
 * Sejak Sesi 5 kelas ini hanya memilih checkout mana yang lewat batas. Pembatalannya sendiri
 * dikerjakan LayananPesanan, pintu yang sama dengan pembatalan oleh pembeli.
 */
final class PembatalanOtomatis
{
    public const ALASAN = 'Tidak dibayar sampai batas waktu';

    public function __construct(private readonly LayananPesanan $pesanan) {}

    /** @return int jumlah checkout yang dibatalkan */
    public function jalankan(): int
    {
        $dibatalkan = 0;

        // Satu transaksi per checkout: kegagalan pada satu checkout tidak menahan yang lain.
        foreach (Pembayaran::query()->lewatBatas()->orderBy('id')->pluck('checkout_id') as $id) {
            $dibatalkan += $this->batalkan($id) ? 1 : 0;
        }

        return $dibatalkan;
    }

    public function batalkan(int $idCheckout): bool
    {
        return $this->pesanan->batalkanCheckout(
            $idCheckout, PelakuPesanan::Sistem, null, self::ALASAN,
        );
    }
}

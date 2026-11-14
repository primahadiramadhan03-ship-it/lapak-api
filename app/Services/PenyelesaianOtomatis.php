<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\Pesanan;

/**
 * LP-12: pesanan yang sudah dikirim sekian hari dan tidak dikonfirmasi pembeli diselesaikan
 * sistem, dan dananya dilepas kepada penjual.
 *
 * Tanpa kelas ini, pembeli yang lupa atau enggan menekan "terima" menahan uang penjual
 * selamanya. Kelas ini hanya MEMILIH pesanannya; penyelesaiannya dikerjakan LayananPesanan,
 * pintu yang sama dengan konfirmasi oleh pembeli.
 */
final class PenyelesaianOtomatis
{
    public function __construct(private readonly LayananPesanan $pesanan) {}

    /** @return int jumlah pesanan yang diselesaikan */
    public function jalankan(): int
    {
        $batas = now()->subDays((int) config('lapak.lama_tahan_hari', 3));
        $selesai = 0;

        $kandidat = Pesanan::query()
            ->where('status', StatusPesanan::Dikirim)
            ->where('dikirim_pada', '<=', $batas)
            ->orderBy('id')
            ->get();

        // Satu transaksi per pesanan: kegagalan pada satu pesanan tidak menahan yang lain.
        foreach ($kandidat as $pesanan) {
            $selesai += $this->selesaikan($pesanan) ? 1 : 0;
        }

        return $selesai;
    }

    public function selesaikan(Pesanan $pesanan): bool
    {
        try {
            $this->pesanan->terima($pesanan, PelakuPesanan::Sistem);
        } catch (TransisiPesananTidakSah) {
            // Daftar kandidat dibaca tanpa kunci. Di antara pembacaan itu dan giliran pesanan
            // ini, pembelinya mengonfirmasi sendiri. Dananya sudah dilepas: tidak ada pekerjaan.
            return false;
        }

        return true;
    }
}

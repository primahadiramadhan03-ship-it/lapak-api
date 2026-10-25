<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Exceptions\SaldoTidakCukup;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BukuBesar
{
    /**
     * LP-13: Memindahkan saldo dari dompet asal ke dompet tujuan.
     * Menghasilkan sepasang mutasi yang jumlahnya nol.
     */
    public function pindahkan(
        Dompet $asal,
        Dompet $tujuan,
        int $jumlahRupiah,
        JenisMutasi $jenis,
        string $rujukan,
        ?string $keterangan = null
    ): void {
        // LP-1: Jumlah harus positif
        if ($jumlahRupiah <= 0) {
            throw new \InvalidArgumentException('Jumlah harus positif.');
        }

        // LP-13: Cek saldo dompet asal (kecuali dompet eksternal boleh negatif)
        if (!$asal->jenis->bolehNegatif() && $asal->saldo_rupiah < $jumlahRupiah) {
            throw new SaldoTidakCukup($asal->saldo_rupiah, $jumlahRupiah);
        }

        // LP-13: Gunakan transaksi database untuk atomicity
        DB::transaction(function () use ($asal, $tujuan, $jumlahRupiah, $jenis, $rujukan, $keterangan) {
            // Kunci baris untuk mencegah race condition
            $asal->lockForUpdate();
            $tujuan->lockForUpdate();

            // Kelompok mutasi (UUID untuk mengelompokkan pasangan)
            $kelompok = Str::uuid()->toString();

            // Mutasi 1: Keluar dari dompet asal (negatif)
            MutasiSaldo::buat(
                $asal->id,
                $kelompok,
                $jenis,
                -$jumlahRupiah,
                $asal->saldo_rupiah - $jumlahRupiah,
                $rujukan,
                $keterangan
            );

            // Mutasi 2: Masuk ke dompet tujuan (positif)
            MutasiSaldo::buat(
                $tujuan->id,
                $kelompok,
                $jenis,
                $jumlahRupiah,
                $tujuan->saldo_rupiah + $jumlahRupiah,
                $rujukan,
                $keterangan
            );

            // Update saldo kedua dompet (TANPA mass assignment)
            $asal->saldo_rupiah = $asal->saldo_rupiah - $jumlahRupiah;
            $asal->save();

            $tujuan->saldo_rupiah = $tujuan->saldo_rupiah + $jumlahRupiah;
            $tujuan->save();
        });
    }

    /**
     * LP-13: Verifikasi keseimbangan buku besar.
     * Total semua mutasi harus nol.
     */
    public function periksaKeseimbangan(): bool
    {
        $total = MutasiSaldo::sum('jumlah_rupiah');
        return $total === 0;
    }
}
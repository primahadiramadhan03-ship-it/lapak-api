<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Exceptions\SaldoTidakCukup;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
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

        // LP-13: Gunakan transaksi database untuk atomicity
        DB::transaction(function () use ($asal, $tujuan, $jumlahRupiah, $jenis, $rujukan, $keterangan) {
            // LP-13: Ambil ulang dan kunci baris urut id menaik agar tidak deadlock.
            $dompet = Dompet::whereKey([$asal->id, $tujuan->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $asalTerkunci = $dompet->get($asal->id);
            $tujuanTerkunci = $dompet->get($tujuan->id);

            // LP-13: Cek saldo setelah baris terkunci (dompet eksternal boleh negatif)
            if (! $asalTerkunci->jenis->bolehNegatif() && $asalTerkunci->saldo_rupiah < $jumlahRupiah) {
                throw new SaldoTidakCukup($asalTerkunci->saldo_rupiah, $jumlahRupiah);
            }

            // Kelompok mutasi (UUID untuk mengelompokkan pasangan)
            $kelompok = Str::uuid()->toString();

            // Mutasi 1: Keluar dari dompet asal (negatif)
            MutasiSaldo::buat(
                $asalTerkunci->id,
                $kelompok,
                $jenis,
                -$jumlahRupiah,
                $asalTerkunci->saldo_rupiah - $jumlahRupiah,
                $rujukan,
                $keterangan
            );

            // Mutasi 2: Masuk ke dompet tujuan (positif)
            MutasiSaldo::buat(
                $tujuanTerkunci->id,
                $kelompok,
                $jenis,
                $jumlahRupiah,
                $tujuanTerkunci->saldo_rupiah + $jumlahRupiah,
                $rujukan,
                $keterangan
            );

            // Update saldo kedua dompet melalui method ubahSaldo()
            $asalTerkunci->ubahSaldo($asalTerkunci->saldo_rupiah - $jumlahRupiah);
            $tujuanTerkunci->ubahSaldo($tujuanTerkunci->saldo_rupiah + $jumlahRupiah);
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

    /**
     * Mencari dompet sistem berdasarkan jenisnya.
     * Digunakan oleh tes dan seeder untuk mendapatkan dompet Eksternal/Escrow/Pendapatan.
     */
    public function dompetSistem(JenisDompet $jenis): Dompet
    {
        return Dompet::query()->where('jenis', $jenis)->firstOrFail();
    }

    /**
     * Mencari dompet penjual berdasarkan ID pengguna.
     * Akan melempar error jika dompet belum dibuat (firstOrFail).
     */
    public function dompetPenjual(Pengguna $penjual): Dompet
    {
        return Dompet::query()->where('kode', 'penjual:'.$penjual->id)->firstOrFail();
    }

    /**
     * Hanya membaca. Dipakai pintu GET agar melihat saldo tidak pernah menulis ke basis data.
     * Mengembalikan null jika pengguna belum memiliki dompet (belum pernah transaksi).
     */
    public function cariDompetPenjual(Pengguna $penjual): ?Dompet
    {
        return Dompet::query()->where('kode', 'penjual:'.$penjual->id)->first();
    }
}

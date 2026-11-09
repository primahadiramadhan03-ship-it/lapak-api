<?php

declare(strict_types=1);

namespace App\Balapan;

use Illuminate\Support\Facades\DB;

/**
 * Skenario kalibrasi runner balapan di MySQL (Modul Hal 26–27).
 * Memverifikasi keserentakan proses dan keterjangkauan basis data.
 */
final class Serempak extends SkenarioBalapan
{
    use PanggilHttp;

    public function nama(): string
    {
        return 'serempak';
    }

    public function deskripsi(): string
    {
        return 'Kalibrasi runner balapan untuk memastikan pembalap serentak terhubung ke basis data (Modul Hal 26–27)';
    }

    public function jumlahPembalap(): int
    {
        return 5;
    }

    public function siapkan(): array
    {
        return [
            'waktu_siap' => microtime(true),
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $mulai = microtime(true);

        try {
            DB::select('SELECT 1 as koneksi');
            $selesai = microtime(true);

            return [
                'pembalap' => $indeks,
                'status' => 'terhubung',
                'mulai' => $mulai,
                'selesai' => $selesai,
                'durasi_ms' => round(($selesai - $mulai) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return [
                'pembalap' => $indeks,
                'status' => 'gagal',
                'galat' => $e->getMessage(),
            ];
        }
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $terhubung = 0;
        $gagal = 0;
        $waktuMulai = [];

        foreach ($hasil as $h) {
            if (($h['status'] ?? null) === 'terhubung') {
                $terhubung++;
                if (isset($h['mulai'])) {
                    $waktuMulai[] = $h['mulai'];
                }
            } else {
                $gagal++;
            }
        }

        $rentangMulaiMs = ! empty($waktuMulai)
            ? round((max($waktuMulai) - min($waktuMulai)) * 1000, 2)
            : 0;

        $valid = $terhubung > 0 && $gagal === 0;

        return [
            'sukses' => $terhubung,
            'gagal' => $gagal,
            'valid' => $valid,
            'pesan' => $valid
                ? "Kalibrasi runner berhasil: seluruh {$terhubung} pembalap serentak terhubung ke basis data (selisih mulai {$rentangMulaiMs} ms)."
                : "Kalibrasi runner gagal: {$gagal} pembalap gagal terhubung ke basis data.",
            'rincian' => [
                'pembalap_terhubung' => $terhubung,
                'pembalap_gagal' => $gagal,
                'selisih_mulai_ms' => $rentangMulaiMs,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Models\LogAudit;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksa keutuhan rantai log audit (LP-19).
 *
 * Ia membaca `log_audit` dari baris pertama sampai terakhir, menghitung ulang setiap hash dari
 * isi yang tersimpan, lalu membandingkan tiga hal:
 *
 *   1. `hash_sebelumnya` tiap baris menyambung ke `hash` baris sebelumnya (yang pertama ke nol);
 *   2. `hash` tiap baris sama dengan hasil hitung ulang dari isinya;
 *   3. kepala rantai menyebut hash dan jumlah baris yang sama dengan isi tabel.
 *
 * Pemeriksa ini HANYA membaca. Ia tidak memperbaiki apa pun: rantai yang sudah terputus tidak
 * dapat disambung tanpa menulis ulang riwayat, dan itulah yang ingin dicegah.
 *
 * @phpstan-type Laporan array{utuh: bool, jumlah: int, kepala: string|null, pelanggaran: list<string>}
 */
final class PemeriksaRantaiAudit
{
    /** @return Laporan */
    public function periksa(): array
    {
        $pelanggaran = [];
        $diharapkan = RantaiHash::awal();
        $jumlah = 0;
        $hashTerakhir = null;

        LogAudit::query()->orderBy('id')->chunk(500, function ($kumpulan) use (
            &$pelanggaran,
            &$diharapkan,
            &$jumlah,
            &$hashTerakhir,
        ): void {
            foreach ($kumpulan as $log) {
                $jumlah++;

                if ($log->hash_sebelumnya !== $diharapkan) {
                    $pelanggaran[] = "Baris {$log->id}: hash_sebelumnya tidak menyambung ke baris sebelumnya.";
                }

                $hitungUlang = RantaiHash::hitung($log->hash_sebelumnya, $log->isiTerlindung());

                if (! hash_equals($log->hash, $hitungUlang)) {
                    $pelanggaran[] = "Baris {$log->id}: isi baris tidak cocok dengan hash yang tersimpan.";
                }

                $diharapkan = $log->hash;
                $hashTerakhir = $log->hash;
            }
        });

        $kepala = DB::table('rantai_audit')->where('id', 1)->first();

        if ($kepala === null) {
            $pelanggaran[] = 'Kepala rantai audit tidak ada; jalankan migrasi.';

            return [
                'utuh' => false,
                'jumlah' => $jumlah,
                'kepala' => null,
                'pelanggaran' => $pelanggaran,
            ];
        }

        if ($hashTerakhir === null) {
            if ($kepala->hash_terakhir !== RantaiHash::awal()) {
                $pelanggaran[] = 'Kepala rantai menyebut hash, tetapi tabel log audit kosong.';
            }
        } elseif (! hash_equals($kepala->hash_terakhir, $hashTerakhir)) {
            $pelanggaran[] = 'Kepala rantai tidak menunjuk baris terakhir log audit.';
        }

        if ((int) $kepala->jumlah !== $jumlah) {
            $pelanggaran[] = "Kepala rantai menyebut {$kepala->jumlah} baris, tabel berisi {$jumlah}.";
        }

        return [
            'utuh' => $pelanggaran === [],
            'jumlah' => $jumlah,
            'kepala' => (string) $kepala->hash_terakhir,
            'pelanggaran' => $pelanggaran,
        ];
    }
}

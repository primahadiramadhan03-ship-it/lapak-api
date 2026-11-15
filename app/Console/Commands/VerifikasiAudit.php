<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Audit\PemeriksaRantaiAudit;
use Illuminate\Console\Command;

/**
 * Memeriksa keutuhan rantai log audit (LP-19).
 *
 * Perintah ini dipakai di dua tempat: kedua tugas CI setelah seluruh pengujian dan uji balapan,
 * dan penjadwalan harian di server. Kode keluar 1 bila rantai tidak utuh, sehingga kegagalan
 * tidak dapat terlewat.
 */
final class VerifikasiAudit extends Command
{
    protected $signature = 'lapak:verifikasi-audit
                            {--rinci : Tampilkan seluruh pelanggaran, bukan hanya lima yang pertama}';

    protected $description = 'Memeriksa keutuhan rantai log audit; kode keluar 1 bila rusak (LP-19).';

    public function handle(PemeriksaRantaiAudit $pemeriksa): int
    {
        $laporan = $pemeriksa->periksa();

        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Jumlah baris log audit', (string) $laporan['jumlah']],
                ['Kepala rantai', $laporan['kepala'] ?? '-'],
                ['Status', $laporan['utuh'] ? 'UTUH' : 'RUSAK'],
            ],
        );

        if ($laporan['utuh']) {
            $this->info("Rantai log audit utuh: {$laporan['jumlah']} baris tersambung sampai kepala rantai.");

            return self::SUCCESS;
        }

        $this->error('Rantai log audit RUSAK. Jangan tambal manual; pulihkan dari cadangan yang masih utuh.');

        $batas = $this->option('rinci') ? count($laporan['pelanggaran']) : 5;

        foreach (array_slice($laporan['pelanggaran'], 0, $batas) as $pelanggaran) {
            $this->line("  - {$pelanggaran}");
        }

        $sisa = count($laporan['pelanggaran']) - $batas;
        if ($sisa > 0) {
            $this->line("  ... dan {$sisa} pelanggaran lain. Jalankan dengan --rinci untuk melihat semuanya.");
        }

        return self::FAILURE;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\SkenarioBalapan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

final class Balapan extends Command
{
    protected $signature = 'lapak:balapan
                            {skenario? : Nama skenario balapan (misal: serempak, IdempotensiGanda)}
                            {--skenario= : Nama skenario balapan}
                            {--pembalap= : Jumlah proses serentak}
                            {--semua : Jalankan seluruh skenario balapan berturut-turut}
                            {--daftar : Tampilkan seluruh skenario yang tersedia}';

    protected $description = 'Menjalankan uji kondisi balapan (race condition) pada MySQL InnoDB';

    public function handle(): int
    {
        $daftarSkenario = $this->temukanSeluruhSkenario();

        if ($this->option('daftar')) {
            $this->tampilkanDaftar($daftarSkenario);

            return self::SUCCESS;
        }

        if ($this->option('semua')) {
            if ($daftarSkenario === []) {
                $this->warn('Belum ada skenario balapan di direktori app/Balapan.');

                return self::SUCCESS;
            }

            $semuaLulus = true;
            $total = count($daftarSkenario);
            $ke = 1;

            $this->info("=== Menjalankan {$total} Skenario Balapan (--semua) ===\n");

            foreach ($daftarSkenario as $nama => $kelas) {
                $this->line("<comment>[{$ke}/{$total}] Skenario: {$nama}</comment>");
                /** @var SkenarioBalapan $skenario */
                $skenario = app($kelas);
                $lulus = $this->eksekusiSkenario($skenario);
                if (! $lulus) {
                    $semuaLulus = false;
                }
                $this->line(str_repeat('-', 60));
                $ke++;
            }

            if ($semuaLulus) {
                $this->info("Seluruh {$total} skenario balapan LULUS tanpa kebocoran!");

                return self::SUCCESS;
            }

            $this->error('Terdapat skenario balapan yang GAGAL atau mendeteksi kebocoran!');

            return self::FAILURE;
        }

        $namaSkenario = (string) ($this->argument('skenario') ?: $this->option('skenario'));
        if ($namaSkenario === '') {
            $pilihan = array_keys($daftarSkenario);
            if ($pilihan === []) {
                $this->warn('Belum ada skenario balapan di direktori app/Balapan.');

                return self::SUCCESS;
            }

            $namaSkenario = $this->choice('Pilih skenario balapan yang akan dijalankan:', $pilihan, $pilihan[0]);
        }

        $kelas = $this->selesaikanKelas($namaSkenario, $daftarSkenario);
        if ($kelas === null || ! class_exists($kelas)) {
            $this->error("Skenario [{$namaSkenario}] tidak ditemukan di app/Balapan.");

            return self::FAILURE;
        }

        /** @var SkenarioBalapan $skenario */
        $skenario = app($kelas);
        $pembalapKustom = $this->option('pembalap') ? (int) $this->option('pembalap') : null;

        return $this->eksekusiSkenario($skenario, $pembalapKustom) ? self::SUCCESS : self::FAILURE;
    }

    private function eksekusiSkenario(SkenarioBalapan $skenario, ?int $pembalapKustom = null): bool
    {
        $jumlahPembalap = $pembalapKustom ?: $skenario->jumlahPembalap();

        $this->info("Menyiapkan skenario: {$skenario->nama()} ({$jumlahPembalap} pembalap serentak)");
        $this->line("Deskripsi: {$skenario->deskripsi()}");

        $konteks = $skenario->siapkan();
        $konteksEncoded = base64_encode(json_encode($konteks));

        $this->comment('Memulai balapan konkurensi...');
        $waktuMulai = microtime(true);

        $perintahList = [];
        $binary = PHP_BINARY;
        $artisan = base_path('artisan');

        $namaKelas = class_basename($skenario);

        foreach (range(1, $jumlahPembalap) as $i) {
            $perintahList["pembalap_{$i}"] = "{$binary} {$artisan} lapak:balapan:anak {$namaKelas} {$i} --konteks={$konteksEncoded}";
        }

        $hasilPool = Process::concurrently(function ($pool) use ($perintahList) {
            foreach ($perintahList as $kunci => $cmd) {
                $pool->as($kunci)->command($cmd);
            }
        });

        $durasi = round(microtime(true) - $waktuMulai, 3);
        $this->info("Balapan selesai dalam {$durasi} detik.\n");

        $hasilPembalap = [];
        foreach (range(1, $jumlahPembalap) as $i) {
            $res = $hasilPool["pembalap_{$i}"];
            $output = $res->output();
            $parsed = json_decode($output, true);

            if ($parsed !== null && isset($parsed['data'])) {
                $hasilPembalap[$i] = $parsed['data'];
            } else {
                $hasilPembalap[$i] = [
                    'pembalap' => $i,
                    'status' => 'error',
                    'raw' => $output ?: $res->errorOutput(),
                ];
            }
        }

        $evaluasi = $skenario->evaluasi($hasilPembalap, $konteks);

        $this->tampilkanHasil($skenario, $hasilPembalap, $evaluasi, $durasi);

        return (bool) $evaluasi['valid'];
    }

    /**
     * @return array<string, class-string<SkenarioBalapan>>
     */
    private function temukanSeluruhSkenario(): array
    {
        $direktori = app_path('Balapan');
        if (! is_dir($direktori)) {
            return [];
        }

        $hasil = [];
        $berkasList = glob($direktori.'/*.php') ?: [];

        foreach ($berkasList as $berkas) {
            $nama = basename($berkas, '.php');
            if ($nama === 'SkenarioBalapan' || $nama === 'PanggilHttp') {
                continue;
            }

            $kelas = 'App\\Balapan\\'.$nama;
            if (class_exists($kelas) && is_subclass_of($kelas, SkenarioBalapan::class)) {
                $hasil[$nama] = $kelas;
            }
        }

        return $hasil;
    }

    /**
     * @param  array<string, class-string<SkenarioBalapan>>  $daftar
     */
    private function selesaikanKelas(string $nama, array $daftar): ?string
    {
        if (isset($daftar[$nama])) {
            return $daftar[$nama];
        }

        foreach ($daftar as $k => $kelas) {
            if (strcasecmp($k, $nama) === 0) {
                return $kelas;
            }

            $instansi = app($kelas);
            if (strcasecmp($instansi->nama(), $nama) === 0) {
                return $kelas;
            }
        }

        return null;
    }

    /**
     * @param  array<string, class-string<SkenarioBalapan>>  $daftar
     */
    private function tampilkanDaftar(array $daftar): void
    {
        $this->table(
            ['Skenario', 'Deskripsi'],
            array_map(function ($kelas) {
                $instansi = app($kelas);

                return [$instansi->nama(), $instansi->deskripsi()];
            }, array_values($daftar))
        );
    }

    /**
     * @param  array<int, mixed>  $hasilPembalap
     * @param  array{sukses: int, gagal: int, valid: bool, pesan: string, rincian: array<string, mixed>}  $evaluasi
     */
    private function tampilkanHasil(SkenarioBalapan $skenario, array $hasilPembalap, array $evaluasi, float $durasi): void
    {
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Skenario', $skenario->nama()],
                ['Deskripsi', $skenario->deskripsi()],
                ['Durasi Balapan', "{$durasi} s"],
                ['Total Pembalap', count($hasilPembalap)],
                ['Sukses / Replay', $evaluasi['sukses']],
                ['Gagal / Ditolak', $evaluasi['gagal']],
                ['Status Integritas', $evaluasi['valid'] ? '✅ AMAN (Integritas Terjaga)' : '❌ BOCOR (Race Condition Terdeteksi)'],
            ]
        );

        $this->line('');
        if ($evaluasi['valid']) {
            $this->info("Hasil Evaluasi: {$evaluasi['pesan']}");
        } else {
            $this->error("Hasil Evaluasi: {$evaluasi['pesan']}");
        }

        if (! empty($evaluasi['rincian'])) {
            $this->line('');
            $this->comment('Rincian Akhir:');
            foreach ($evaluasi['rincian'] as $k => $v) {
                $this->line("  - {$k}: ".(is_bool($v) ? ($v ? 'true' : 'false') : $v));
            }
        }
    }
}

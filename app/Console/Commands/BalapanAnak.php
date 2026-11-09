<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\SkenarioBalapan;
use Illuminate\Console\Command;

final class BalapanAnak extends Command
{
    protected $signature = 'lapak:balapan:anak
                            {skenario : Nama kelas skenario}
                            {indeks : Nomor urut pembalap}
                            {--konteks= : Data konteks serialisasi JSON base64}';

    protected $description = 'Pembalap anak untuk simulasi kondisi balapan konkurensi';

    public function handle(): int
    {
        $namaSkenario = (string) $this->argument('skenario');
        $indeks = (int) $this->argument('indeks');
        $konteksRaw = (string) $this->option('konteks');

        $konteks = $konteksRaw !== ''
            ? json_decode(base64_decode($konteksRaw), true) ?? []
            : [];

        $kelas = $this->temukanKelas($namaSkenario);

        if ($kelas === null || ! class_exists($kelas)) {
            $this->output->write(json_encode([
                'sukses' => false,
                'galat' => "Kelas skenario [{$namaSkenario}] tidak ditemukan.",
            ]));

            return self::FAILURE;
        }

        /** @var SkenarioBalapan $skenario */
        $skenario = app($kelas);

        try {
            $hasil = $skenario->jalankan($indeks, $konteks);
            $this->output->write(json_encode([
                'sukses' => true,
                'data' => $hasil,
            ]));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->output->write(json_encode([
                'sukses' => false,
                'galat' => $e->getMessage(),
            ]));

            return self::FAILURE;
        }
    }

    private function temukanKelas(string $nama): ?string
    {
        if (str_starts_with($nama, 'App\\Balapan\\') && class_exists($nama)) {
            return $nama;
        }

        $kandidatKapital = 'App\\Balapan\\'.ucfirst($nama);
        if (class_exists($kandidatKapital)) {
            return $kandidatKapital;
        }

        $kandidatBiasa = 'App\\Balapan\\'.$nama;
        if (class_exists($kandidatBiasa)) {
            return $kandidatBiasa;
        }

        $direktori = app_path('Balapan');
        $berkasList = glob($direktori.'/*.php') ?: [];
        foreach ($berkasList as $berkas) {
            $base = basename($berkas, '.php');
            if ($base === 'SkenarioBalapan' || $base === 'PanggilHttp') {
                continue;
            }

            $k = 'App\\Balapan\\'.$base;
            if (class_exists($k) && is_subclass_of($k, SkenarioBalapan::class)) {
                if (strcasecmp(app($k)->nama(), $nama) === 0 || strcasecmp($base, $nama) === 0) {
                    return $k;
                }
            }
        }

        return null;
    }
}

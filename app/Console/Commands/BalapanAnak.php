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

        $kelas = str_starts_with($namaSkenario, 'App\\Balapan\\')
            ? $namaSkenario
            : 'App\\Balapan\\'.$namaSkenario;

        if (! class_exists($kelas)) {
            $this->output->write(json_encode([
                'sukses' => false,
                'galat' => "Kelas skenario [{$kelas}] tidak ditemukan.",
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
}

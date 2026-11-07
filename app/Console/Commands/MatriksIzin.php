<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NamaIzin;
use App\Enums\NamaPeran;
use Illuminate\Console\Command;

final class MatriksIzin extends Command
{
    protected $signature = 'lapak:matriks-izin';

    protected $description = 'Menampilkan matriks izin per peran';

    public function handle(): int
    {
        $peran = NamaPeran::cases();
        $baris = [];

        foreach (NamaIzin::cases() as $izin) {
            $baris[] = [
                'izin' => $izin->value,
                ...array_combine(
                    array_map(static fn (NamaPeran $nama): string => $nama->value, $peran),
                    array_map(
                        static fn (NamaPeran $nama): string => in_array($izin, $nama->izinBawaan(), true) ? 'v' : '-',
                        $peran,
                    ),
                ),
                'keterangan' => $izin->keterangan(),
            ];
        }

        $this->table(
            ['Izin', ...array_map(static fn (NamaPeran $nama): string => $nama->value, $peran), 'Keterangan'],
            $baris,
        );

        return self::SUCCESS;
    }
}

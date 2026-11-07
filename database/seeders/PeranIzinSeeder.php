<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NamaIzin;
use App\Enums\NamaPeran;
use App\Models\Izin;
use App\Models\Peran;
use App\Services\PemeriksaIzin;
use Illuminate\Database\Seeder;

class PeranIzinSeeder extends Seeder
{
    public function run(): void
    {
        $izin = [];

        foreach (NamaIzin::cases() as $nama) {
            $izin[$nama->value] = Izin::query()->updateOrCreate(
                ['nama' => $nama->value],
                ['keterangan' => $nama->keterangan()],
            );
        }

        foreach (NamaPeran::cases() as $nama) {
            $peran = Peran::query()->updateOrCreate(['nama' => $nama->value]);

            $peran->izin()->sync(array_map(
                static fn (NamaIzin $namaIzin): int => $izin[$namaIzin->value]->getKey(),
                $nama->izinBawaan(),
            ));
        }

        // Pemetaan mungkin baru saja berubah: izin yang telanjur diingat tidak boleh dipakai lagi.
        app(PemeriksaIzin::class)->lupakanSemua();
    }
}

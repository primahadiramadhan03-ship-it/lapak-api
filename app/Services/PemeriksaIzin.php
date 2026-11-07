<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaIzin;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Builder;

/**
 * Menjawab satu pertanyaan pada setiap pemeriksaan otorisasi:
 * apakah pengguna ini memegang izin tersebut melalui salah satu perannya?
 */
final class PemeriksaIzin
{
    public function boleh(Pengguna $pengguna, NamaIzin $izin): bool
    {
        return Peran::query()
            ->whereHas('pengguna', fn (Builder $kueri) => $kueri->where('pengguna.id', $pengguna->getKey()))
            ->whereHas('izin', fn (Builder $kueri) => $kueri->where('izin.nama', $izin->value))
            ->exists();
    }

    /**
     * Seluruh izin yang dimiliki pengguna, tanpa duplikat.
     *
     * @return list<NamaIzin>
     */
    public function semua(Pengguna $pengguna): array
    {
        $nama = Izin::query()
            ->whereHas('peran.pengguna', fn (Builder $kueri) => $kueri->where('pengguna.id', $pengguna->getKey()))
            ->pluck('nama')
            ->map(static fn (NamaIzin|string $nama): NamaIzin => $nama instanceof NamaIzin ? $nama : NamaIzin::from($nama))
            ->unique()
            ->values()
            ->all();

        return $nama;
    }
}

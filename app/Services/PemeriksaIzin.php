<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaIzin;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Menjawab satu pertanyaan pada setiap pemeriksaan otorisasi:
 * apakah pengguna ini memegang izin tersebut melalui salah satu perannya?
 */
final class PemeriksaIzin
{
    public function punya(Pengguna $pengguna, NamaIzin $izin): bool
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
    public function daftar(Pengguna $pengguna): array
    {
        return Izin::query()
            ->whereHas('peran.pengguna', fn (Builder $kueri) => $kueri->where('pengguna.id', $pengguna->getKey()))
            ->pluck('nama')
            ->map(static fn (NamaIzin|string $nama): NamaIzin => $nama instanceof NamaIzin ? $nama : NamaIzin::from($nama))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Ambil daftar izin dengan cache 5 menit. Dipanggil oleh middleware otorisasi.
     *
     * @return list<NamaIzin>
     */
    public function daftarDiingat(Pengguna $pengguna): array
    {
        return Cache::remember(
            $this->kunciIzin($pengguna),
            60 * 5,
            fn () => $this->daftar($pengguna),
        );
    }

    /**
     * Hook ingatan izin. Dipanggil saat peran berubah (beri/cabut).
     */
    public function lupakan(Pengguna $pengguna): void
    {
        Cache::forget($this->kunciIzin($pengguna));
    }

    private function kunciIzin(Pengguna $pengguna): string
    {
        return "izin:pengguna:{$pengguna->getKey()}";
    }
}

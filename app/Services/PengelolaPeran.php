<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaPeran;
use App\Enums\PeristiwaAudit;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Database\UniqueConstraintViolationException;
use LogicException;

final class PengelolaPeran
{
    public function __construct(
        private readonly PemeriksaIzin $izin,
        private readonly PenerbitSesi $sesi,
        private readonly PencatatAudit $audit,
    ) {}

    public function beri(
        Pengguna $pengguna,
        NamaPeran $peran,
        ?Pengguna $oleh = null
    ): bool {
        $idPeran = $this->idPeran($peran);

        if ($pengguna->peran()->where('peran.id', $idPeran)->exists()) {
            return false;
        }

        try {
            $pengguna->peran()->attach($idPeran, [
                'diberikan_oleh' => $oleh?->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        $this->setelahBerubah($pengguna);

        $this->audit->catat(
            PeristiwaAudit::PeranDiberikan,
            $oleh,
            "pengguna:{$pengguna->id}",
            ['peran' => $peran->value],
        );

        return true;
    }

    public function cabut(
        Pengguna $pengguna,
        NamaPeran $peran,
        ?Pengguna $oleh = null
    ): bool {
        if ($pengguna->peran()->detach($this->idPeran($peran)) === 0) {
            return false;
        }

        $this->setelahBerubah($pengguna);

        $this->audit->catat(
            PeristiwaAudit::PeranDicabut,
            $oleh,
            "pengguna:{$pengguna->id}",
            ['peran' => $peran->value],
        );

        return true;
    }

    /**
     * @return list<string>
     */
    public function daftar(Pengguna $pengguna): array
    {
        return $pengguna->peran()
            ->orderBy('peran.nama')
            ->pluck('peran.nama')
            ->map(
                static fn (NamaPeran|string $nama): string => $nama instanceof NamaPeran
                    ? $nama->value
                    : $nama
            )
            ->values()
            ->all();
    }

    private function setelahBerubah(Pengguna $pengguna): void
    {
        $this->izin->lupakan($pengguna);
        $this->sesi->cabutSemua($pengguna);
    }

    private function idPeran(NamaPeran $peran): int
    {
        $id = Peran::query()
            ->where('nama', $peran->value)
            ->value('id');

        if ($id === null) {
            throw new LogicException(
                "Peran {$peran->value} belum ada di basis data. Jalankan PeranIzinSeeder."
            );
        }

        return (int) $id;
    }
}

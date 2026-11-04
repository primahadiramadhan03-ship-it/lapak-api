<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sesi;


final class PangkasSesi
{
    
    public function hapusSesiKedaluwarsa(): int
    {
        return Sesi::query()
            ->where('kedaluwarsa_pada', '<', now())
            ->delete();
    }

    
    public function hapusSesiMenganggur(): int
    {
        $batasMenganggur = now()->subMinutes(config('lapak.sesi.menganggur_menit', 120));

        return Sesi::query()
            ->where('terakhir_dipakai_pada', '<', $batasMenganggur)
            ->delete();
    }

    
    public function jalankan(): int
    {
        $kedaluwarsa = $this->hapusSesiKedaluwarsa();
        $menganggur = $this->hapusSesiMenganggur();

        return $kedaluwarsa + $menganggur;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Sesi\KunciSesi;
use App\Enums\StatusAkun;
use App\Models\Sesi;

/**
 * Memeriksa apakah kunci dimiliki sesi yang masih berlaku.
 *
 * Seluruh kegagalan menghasilkan null yang sama.
 */
final class PemeriksaSesi
{
    public function periksa(?string $teksKunci): ?Sesi
    {
        $kunci = KunciSesi::urai($teksKunci ?? '');

        if ($kunci === null) {
            return null;
        }

        $sesi = Sesi::query()
            ->berlaku()
            ->with('pengguna')
            ->where('pengenal', $kunci->pengenal)
            ->first();

        if ($sesi === null || ! $kunci->cocok($sesi->hash_kunci)) {
            return null;
        }

        // Akun yang ditangguhkan kehilangan akses pada permintaan berikutnya.
        if ($sesi->pengguna->status !== StatusAkun::Aktif) {
            return null;
        }

        $this->sentuh($sesi);

        return $sesi;
    }

    /**
     * Mencatat pemakaian paling sering satu kali per jeda.
     */
    private function sentuh(Sesi $sesi): void
    {
        $batas = now()->subSeconds(
            config('lapak.sesi.jeda_sentuh_detik')
        );

        if ($sesi->terakhir_dipakai_pada->lt($batas)) {
            $sesi->forceFill([
                'terakhir_dipakai_pada' => now(),
            ])->save();
        }
    }
}

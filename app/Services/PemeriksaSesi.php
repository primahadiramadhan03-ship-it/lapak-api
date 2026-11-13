<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Sesi\KunciSesi;
use App\Enums\PeristiwaAudit;
use App\Enums\StatusAkun;
use App\Models\Sesi;

/**
 * Menjawab satu pertanyaan pada setiap permintaan: kunci ini milik sesi yang masih berlaku?
 * Seluruh kegagalan menghasilkan null. Pemakaian kunci lama mematikan sesi dan dicatat ke log audit.
 */
final class PemeriksaSesi
{
    public function __construct(
        private readonly PenerbitSesi $penerbit,
        private readonly PencatatAudit $audit,
    ) {}

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

        if ($sesi === null) {
            return null;
        }

        // Deteksi pemakaian kunci lama setelah rotasi: cabut sesi dan catat jejak audit
        if ($sesi->hash_kunci_lama !== null && $kunci->cocok($sesi->hash_kunci_lama)) {
            $this->penerbit->cabut($sesi);
            $this->audit->catat(
                PeristiwaAudit::KunciLamaDipakai,
                $sesi->pengguna,
                "sesi:{$sesi->pengenal}",
                ['pengenal' => $sesi->pengenal],
            );

            return null;
        }

        if (! $kunci->cocok($sesi->hash_kunci)) {
            return null;
        }

        // Akun yang ditangguhkan kehilangan aksesnya pada permintaan berikutnya,
        // tanpa menunggu kuncinya kedaluwarsa.
        if ($sesi->pengguna->status !== StatusAkun::Aktif) {
            return null;
        }

        $this->sentuh($sesi);

        return $sesi;
    }

    /** Mencatat pemakaian paling sering satu kali per jeda, agar tidak menulis pada tiap permintaan. */
    private function sentuh(Sesi $sesi): void
    {
        $batas = now()->subSeconds((int) config('lapak.sesi.jeda_sentuh_detik', 60));

        if ($sesi->terakhir_dipakai_pada->lt($batas)) {
            $sesi->forceFill(['terakhir_dipakai_pada' => now()])->save();
        }
    }
}

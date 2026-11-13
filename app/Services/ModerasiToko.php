<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NamaPeran;
use App\Enums\PeristiwaAudit;
use App\Enums\StatusToko;
use App\Exceptions\TransisiTokoTidakSah;
use App\Models\Pengguna;
use App\Models\Toko;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan admin atas toko (LP-3).
 *
 * menunggu --setujui--> aktif --tangguhkan--> ditangguhkan --setujui--> aktif
 *
 * Peran penjual lahir di sini dan hanya di sini: saat sebuah toko disetujui.
 */
final class ModerasiToko
{
    public function __construct(
        private readonly PengelolaPeran $peran,
        private readonly PencatatAudit $audit,
    ) {}

    public function setujui(Toko $toko, Pengguna $admin): Toko
    {
        // Status toko dan peran pemiliknya berubah bersama atau tidak sama sekali.
        return DB::transaction(function () use ($toko, $admin): Toko {
            $asal = [StatusToko::Menunggu, StatusToko::Ditangguhkan];
            $this->pindahkan($toko, $asal, StatusToko::Aktif, $admin);

            // Pemilik toko yang dipulihkan sudah memegang peran ini: beri() mengembalikan false
            // dan sesinya tidak diganggu. Pemilik toko baru kehilangan sesinya dan masuk ulang.
            $this->peran->beri($toko->pemilik()->firstOrFail(), NamaPeran::Penjual, $admin);

            $toko->refresh();

            // Jejak moderasi: catat di akhir transaksi (LP-17)
            $this->audit->catat(
                PeristiwaAudit::TokoDisetujui,
                $admin,
                "toko:{$toko->id}",
                ['toko_id' => $toko->id, 'nama_toko' => $toko->nama],
            );

            return $toko;
        });
    }

    /**
     * Peran penjual TIDAK dicabut: pemilik tetap perlu melihat dompet dan membenahi produknya.
     * Yang menutup tokonya dari publik adalah status toko (LP-4), bukan hilangnya peran.
     */
    public function tangguhkan(Toko $toko, Pengguna $admin, string $alasan): Toko
    {
        return DB::transaction(function () use ($toko, $admin, $alasan): Toko {
            $this->pindahkan($toko, [StatusToko::Aktif], StatusToko::Ditangguhkan, $admin, $alasan);

            $toko->refresh();

            // Jejak moderasi: catat di akhir transaksi (LP-17)
            $this->audit->catat(
                PeristiwaAudit::TokoDitangguhkan,
                $admin,
                "toko:{$toko->id}",
                ['toko_id' => $toko->id, 'nama_toko' => $toko->nama, 'alasan' => $alasan],
            );

            return $toko;
        });
    }

    /**
     * @param  list<StatusToko>  $dari
     */
    private function pindahkan(
        Toko $toko,
        array $dari,
        StatusToko $ke,
        Pengguna $admin,
        ?string $catatan = null,
    ): void {
        // Syarat status asal ikut di dalam UPDATE, tidak diperiksa lebih dulu dengan SELECT.
        // Bila dua admin memutuskan toko yang sama bersamaan, hanya satu UPDATE yang mengenai baris.
        $berubah = Toko::query()
            ->whereKey($toko->id)
            ->whereIn('status', $dari)
            ->update([
                'status' => $ke,
                'dimoderasi_oleh' => $admin->id,
                'dimoderasi_pada' => now(),
                'catatan_moderasi' => $catatan,
            ]);

        if ($berubah === 0) {
            throw new TransisiTokoTidakSah($ke);
        }
    }
}

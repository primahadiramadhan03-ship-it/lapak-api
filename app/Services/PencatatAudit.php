<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PencatatAudit
{
    private const TERLARANG = ['kata_sandi', 'kunci', 'hash_kunci', 'rahasia', 'tanda_tangan'];

    public function catat(
        PeristiwaAudit $peristiwa,
        ?Pengguna $pelaku = null,
        ?string $sasaran = null,
        array $rincian = [],
    ): LogAudit {
        $this->tolakRahasia($rincian);

        return DB::transaction(function () use ($peristiwa, $pelaku, $sasaran, $rincian): LogAudit {
            $kepala = DB::table('rantai_audit')->where('id', 1)->first();

            if ($kepala === null) {
                throw new LogicException('Kepala rantai audit tidak ada. Jalankan migrasi.');
            }

            $log = new LogAudit;
            $log->forceFill([
                'peristiwa' => $peristiwa->value,
                'pelaku_id' => $pelaku?->id,
                'sasaran' => $sasaran,
                'rincian' => $rincian,
                'id_permintaan' => request()->attributes->get('id_permintaan'),
                'dicatat_pada' => now()->startOfSecond(),
                'hash_sebelumnya' => $kepala->hash_terakhir,
            ]);

            $log->hash = RantaiHash::hitung($kepala->hash_terakhir, $log->isiTerlindung());
            $log->save();

            DB::table('rantai_audit')->where('id', 1)->update([
                'hash_terakhir' => $log->hash,
                'jumlah' => $kepala->jumlah + 1,
            ]);

            return $log;
        });
    }

    private function tolakRahasia(array $rincian): void
    {
        foreach ($rincian as $kunci => $nilai) {
            if (is_string($kunci) && in_array($kunci, self::TERLARANG, true)) {
                throw new LogicException("Rincian audit tidak boleh memuat \"{$kunci}\".");
            }

            if (is_array($nilai)) {
                $this->tolakRahasia($nilai);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GalatDomain;
use App\Models\Alamat;
use App\Models\Pengguna;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BukuAlamat
{
    public function tambah(Pengguna $pemilik, array $data): Alamat
    {
        return $this->terkunci($pemilik, function (Collection $alamat) use ($pemilik, $data) {
            if ($alamat->count() >= config('lapak.transaksi.alamat_maksimum')) {
                throw new GalatDomain(
                    'ALAMAT_PENUH',
                    'Jumlah alamat sudah mencapai batas.',
                    422
                );
            }

            $baru = new Alamat($data);
            $baru->pengguna_id = $pemilik->id;
            $baru->utama = $alamat->isEmpty();
            $baru->save();

            return $baru;
        });
    }

    public function jadikanUtama(Pengguna $pemilik, int $idAlamat): Alamat
    {
        return $this->terkunci($pemilik, function (Collection $alamat) use ($idAlamat) {
            $terpilih = $alamat->firstWhere('id', $idAlamat);

            abort_if($terpilih === null, 404);

            foreach ($alamat as $item) {
                $item->forceFill([
                    'utama' => $item->is($terpilih),
                ])->save();
            }

            return $terpilih->refresh();
        });
    }

    public function hapus(Pengguna $pemilik, int $idAlamat): void
    {
        $this->terkunci($pemilik, function (Collection $alamat) use ($idAlamat) {
            $terpilih = $alamat->firstWhere('id', $idAlamat);

            abort_if($terpilih === null, 404);

            $adalahUtama = $terpilih->utama;

            $terpilih->delete();

            if ($adalahUtama) {
                $pengganti = $alamat
                    ->reject(fn (Alamat $item) => $item->is($terpilih))
                    ->first();

                if ($pengganti !== null) {
                    $pengganti->forceFill(['utama' => true])->save();
                }
            }
        });
    }

    private function terkunci(Pengguna $pemilik, Closure $kerja): mixed
    {
        return DB::transaction(function () use ($pemilik, $kerja) {
            Pengguna::query()
                ->whereKey($pemilik->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alamat = Alamat::query()
                ->milik($pemilik)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return $kerja($alamat);
        });
    }
}

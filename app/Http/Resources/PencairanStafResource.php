<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Uang;
use App\Models\Pencairan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pencairan
 */
final class PencairanStafResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $uang = Uang::dari($this->jumlah_rupiah);

        return [
            'kode' => $this->kode,
            'jumlah' => [
                'rupiah' => $uang->rupiah,
                'tampilan' => $uang->format(),
            ],
            'pengaju' => [
                'id' => $this->pengaju->id,
                'nama' => $this->pengaju->nama,
            ],
            'rekening_tujuan' => $this->rekening_tujuan,
            'status' => $this->status->value,
            'diputuskan_oleh' => $this->pemutus?->nama,
            'diputuskan_pada' => $this->diputuskan_pada?->toIso8601String(),
            'catatan' => $this->catatan,
            'diajukan_pada' => $this->created_at->toIso8601String(),
        ];
    }
}

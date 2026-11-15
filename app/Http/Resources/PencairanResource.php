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
final class PencairanResource extends JsonResource
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
            'rekening_tujuan' => $this->rekening_tujuan,
            'status' => $this->status->value,
            'catatan' => $this->catatan,
            'diajukan_pada' => $this->created_at->toIso8601String(),
            'diputuskan_pada' => $this->diputuskan_pada?->toIso8601String(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MutasiSaldo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mutasi seperti yang dilihat staf: sama dengan yang dilihat pemilik dompet, ditambah nomor
 * baris dan kelompok. Dengan kelompok, staf menemukan baris pasangannya di dompet lawan (LP-13).
 *
 * @mixin MutasiSaldo
 */
final class MutasiBukuBesarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kelompok' => $this->kelompok,
            ...MutasiResource::make($this->resource)->toArray($request),
        ];
    }
}

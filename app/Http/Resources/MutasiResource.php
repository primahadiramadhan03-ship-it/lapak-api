<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Uang;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class MutasiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $jumlah = Uang::dari(abs($this->jumlah_rupiah));

        return [
            'id' => $this->id,
            'jenis' => $this->jenis->value,
            'jumlah' => [
                'rupiah' => $this->jumlah_rupiah,
                'tampilan' => $jumlah->format(),
            ],
            'saldo_setelah' => [
                'rupiah' => $this->saldo_setelah,
                'tampilan' => Uang::dari($this->saldo_setelah)->format(),
            ],
            'rujukan' => $this->rujukan,
            'keterangan' => $this->keterangan,
            'waktu' => $this->created_at->toISOString(),
        ];
    }
}

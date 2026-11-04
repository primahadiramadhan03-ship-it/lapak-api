<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DompetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'kode' => $this->kode,
            'jenis' => $this->jenis->value,
            'saldo_rupiah' => $this->saldo_rupiah,
        ];
    }
}

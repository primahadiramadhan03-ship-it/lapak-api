<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Toko;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Toko
 */
final class TokoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'slug' => $this->slug,
            'kota' => $this->kota,
            'deskripsi' => $this->deskripsi,
            'ongkir_rupiah' => $this->ongkir_rupiah,
            'ongkir' => 'Rp ' . number_format($this->ongkir_rupiah, 0, ',', '.'),
            'status' => $this->status->value,
            'pemilik' => PenggunaResource::make($this->whenLoaded('pemilik')),
            'produk' => ProdukResource::collection($this->whenLoaded('produk')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

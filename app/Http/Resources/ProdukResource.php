<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Produk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Produk
 */
final class ProdukResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'slug' => $this->slug,
            'deskripsi' => $this->deskripsi,
            'harga_rupiah' => $this->harga_rupiah,
            'harga' => 'Rp '.number_format($this->harga_rupiah, 0, ',', '.'),
            'stok' => $this->stok,
            'berat_gram' => $this->berat_gram,
            'status' => $this->status->value,
            'kategori' => KategoriResource::make($this->whenLoaded('kategori')),
            'toko' => TokoResource::make($this->whenLoaded('toko')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

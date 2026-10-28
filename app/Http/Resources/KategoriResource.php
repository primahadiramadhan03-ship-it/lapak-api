<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Kategori
 */
final class KategoriResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'slug' => $this->slug,
        ];
    }
}

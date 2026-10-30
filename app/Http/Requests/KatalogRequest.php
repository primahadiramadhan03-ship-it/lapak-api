<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Katalog adalah endpoint publik, tidak perlu autentikasi.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cari' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:80'],
            'harga_min' => ['nullable', 'integer', 'min:0'],
            'harga_max' => ['nullable', 'integer', 'min:0', 'gt:harga_min'],
            'urut' => ['nullable', Rule::in(['nama', 'harga', 'terbaru', 'stok'])],
            'arah' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}

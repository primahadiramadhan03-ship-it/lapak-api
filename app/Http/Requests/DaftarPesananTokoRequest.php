<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Pesanan\StatusPesanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DaftarPesananTokoRequest extends FormRequest
{
    /** Kewenangan sudah diputuskan middleware izin pada rute. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(StatusPesanan::class)],
            'halaman' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

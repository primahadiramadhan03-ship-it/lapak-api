<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Uang;
use Illuminate\Foundation\Http\FormRequest;

final class AjukanPencairanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'jumlah_rupiah' => ['required', 'integer', 'min:1'],
            'rekening_tujuan' => ['required', 'string', 'max:80'],
        ];
    }

    public function jumlah(): Uang
    {
        return Uang::dari((int) $this->validated('jumlah_rupiah'));
    }

    public function rekeningTujuan(): string
    {
        return (string) $this->validated('rekening_tujuan');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TolakPencairanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'catatan' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function catatan(): ?string
    {
        return $this->validated('catatan');
    }
}

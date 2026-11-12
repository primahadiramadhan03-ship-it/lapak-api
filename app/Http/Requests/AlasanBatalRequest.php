<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AlasanBatalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'max:255'],
        ];
    }

    public function alasan(): string
    {
        return (string) $this->validated('alasan');
    }
}


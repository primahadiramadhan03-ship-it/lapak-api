<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegistrasiRequest;
use App\Http\Resources\PenggunaResource;
use App\Models\Pengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

final class RegistrasiController extends Controller
{
    public function __invoke(RegistrasiRequest $request): JsonResponse
    {
        $data = $request->safe()->only([
            'nama',
            'surel',
            'telepon',
            'kata_sandi',
        ]);

        $data['kata_sandi'] = Hash::make($data['kata_sandi']);

        $pengguna = Pengguna::create($data)->refresh();

        return $this->sukses(
            PenggunaResource::make($pengguna),
            201
        );
    }
}

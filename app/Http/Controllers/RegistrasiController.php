<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NamaPeran;
use App\Http\Requests\RegistrasiRequest;
use App\Http\Resources\PenggunaResource;
use App\Models\Pengguna;
use App\Services\PengelolaPeran;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class RegistrasiController extends Controller
{
    public function __invoke(
        RegistrasiRequest $request,
        PengelolaPeran $pengelola
    ): JsonResponse {
        // Akun dan peran pertamanya dibuat bersama atau dibatalkan bersama.
        $pengguna = DB::transaction(function () use ($request, $pengelola): Pengguna {
            // Hanya mengambil kolom yang lolos validasi.
            $pengguna = Pengguna::create(
                $request->safe()->only([
                    'nama',
                    'surel',
                    'telepon',
                    'kata_sandi',
                ])
            )->refresh();

            // Peran awal ditentukan oleh server, bukan oleh klien.
            $pengelola->beri($pengguna, NamaPeran::Pembeli);

            return $pengguna;
        });

        return $this->sukses(
            PenggunaResource::make($pengguna->load('peran')),
            201
        );
    }
}

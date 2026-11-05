<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MutasiResource;
use App\Services\BukuBesar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RiwayatMutasiController extends Controller
{
    public function __construct(private readonly BukuBesar $buku) {}

    public function index(Request $request): JsonResponse
    {
        $dompet = $this->buku->cariDompetPenjual($request->user());

        if ($dompet === null) {
            return response()->json([
                'sukses' => true,
                'meta' => null,
                'data' => [],
            ]);
        }

        // Gunakan config lapak.katalog.per_halaman_bawaan (12)
        $perHalaman = config('lapak.katalog.per_halaman_bawaan', 12);

        $mutasi = $dompet->mutasi()
            ->orderByDesc('created_at')
            ->paginate($perHalaman);

        return response()->json([
            'sukses' => true,
            'meta' => [
                'halaman' => $mutasi->currentPage(),
                'per_halaman' => $mutasi->perPage(),
                'total' => $mutasi->total(),
                'halaman_terakhir' => $mutasi->lastPage(),
            ],
            'data' => MutasiResource::collection($mutasi),
        ]);
    }
}

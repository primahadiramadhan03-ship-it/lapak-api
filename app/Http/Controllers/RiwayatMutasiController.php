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

        // Pagination dengan 20 item per halaman
        $mutasi = $dompet->mutasi()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'sukses' => true,
            'meta' => [
                'current_page' => $mutasi->currentPage(),
                'last_page' => $mutasi->lastPage(),
                'per_page' => $mutasi->perPage(),
                'total' => $mutasi->total(),
            ],
            'data' => MutasiResource::collection($mutasi),
        ]);
    }
}

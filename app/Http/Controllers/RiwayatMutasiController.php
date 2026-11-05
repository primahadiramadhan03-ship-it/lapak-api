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

        // Gunakan konfigurasi jumlah mutasi per halaman
        $perHalaman = (int) config('lapak.katalog.per_halaman_bawaan', 12);

        $mutasi = $dompet->mutasi()
            ->orderByDesc('id')
            ->paginate($perHalaman);

        return $this->berhalaman($mutasi, MutasiResource::class);
    }
}

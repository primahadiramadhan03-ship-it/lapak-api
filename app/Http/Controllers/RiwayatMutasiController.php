<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        // Urutkan berdasarkan created_at descending (terbaru di atas)
        $mutasi = $dompet->mutasi()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'kelompok' => $m->kelompok,
                'jenis' => $m->jenis->value,
                'jumlah_rupiah' => $m->jumlah_rupiah,
                'saldo_setelah' => $m->saldo_setelah,
                'rujukan' => $m->rujukan,
                'keterangan' => $m->keterangan,
                'dibuat_pada' => $m->created_at->toISOString(), // Gunakan created_at
            ]);

        return response()->json([
            'sukses' => true,
            'meta' => null,
            'data' => $mutasi,
        ]);
    }
}

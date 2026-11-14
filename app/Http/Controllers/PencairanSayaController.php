<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AjukanPencairanRequest;
use App\Http\Resources\PencairanResource;
use App\Models\Pencairan;
use App\Services\LayananPencairan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PencairanSayaController extends Controller
{
    /**
     * GET /api/v1/dompet/pencairan: melihat riwayat pengajuan pencairan sendiri.
     */
    public function index(Request $request): JsonResponse
    {
        $halaman = Pencairan::query()
            ->where('pengaju_id', $request->user()->id)
            ->latest('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan', 12));

        return $this->berhalaman($halaman, PencairanResource::class);
    }

    /**
     * POST /api/v1/dompet/pencairan: mengajukan pencairan saldo penjual.
     */
    public function store(AjukanPencairanRequest $request, LayananPencairan $layanan): JsonResponse
    {
        $pencairan = $layanan->ajukan(
            $request->user(),
            $request->jumlah(),
            $request->rekeningTujuan(),
        );

        return $this->sukses(PencairanResource::make($pencairan), 201);
    }
}

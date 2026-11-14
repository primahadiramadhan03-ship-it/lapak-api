<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TolakPencairanRequest;
use App\Http\Resources\PencairanStafResource;
use App\Models\Pencairan;
use App\Services\LayananPencairan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PencairanStafController extends Controller
{
    /**
     * GET /api/v1/keuangan/pencairan: daftar pengajuan pencairan untuk staf keuangan.
     */
    public function index(Request $request): JsonResponse
    {
        $halaman = Pencairan::query()
            ->with(['pengaju', 'pemutus'])
            ->when(
                $request->query('status'),
                fn (Builder $kueri, string $status) => $kueri->where('status', $status),
            )
            ->latest('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan', 12));

        return $this->berhalaman($halaman, PencairanStafResource::class);
    }

    /**
     * POST /api/v1/keuangan/pencairan/{kode}/setujui: staf keuangan menyetujui pencairan.
     */
    public function setujui(Request $request, LayananPencairan $layanan, string $kode): JsonResponse
    {
        $pencairan = Pencairan::query()->where('kode', $kode)->firstOrFail();
        $terkini = $layanan->setujui($pencairan, $request->user());

        return $this->sukses(PencairanStafResource::make($terkini->load(['pengaju', 'pemutus'])));
    }

    /**
     * POST /api/v1/keuangan/pencairan/{kode}/tolak: staf keuangan menolak pencairan.
     */
    public function tolak(TolakPencairanRequest $request, LayananPencairan $layanan, string $kode): JsonResponse
    {
        $pencairan = Pencairan::query()->where('kode', $kode)->firstOrFail();
        $terkini = $layanan->tolak($pencairan, $request->user(), $request->catatan());

        return $this->sukses(PencairanStafResource::make($terkini->load(['pengaju', 'pemutus'])));
    }
}

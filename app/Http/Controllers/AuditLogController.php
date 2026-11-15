<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PeristiwaAudit;
use App\Http\Resources\LogAuditResource;
use App\Models\LogAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pintu baca auditor ke log audit (LP-19). Seluruh rutenya hanya-baca dan berada di belakang
 * izin audit.lihat serta sesi segar.
 *
 * Rute ini sengaja TIDAK berada di bawah /buku-besar: awalan itu milik domain uang, sedangkan
 * log audit adalah jejak seluruh domain. Memisahkannya menjaga arti kedua awalan.
 */
final class AuditLogController extends Controller
{
    /** GET /audit/log: jejak terbaru lebih dulu, dapat disaring peristiwa, sasaran, dan pelaku. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'peristiwa' => ['nullable', 'string', Rule::enum(PeristiwaAudit::class)],
            'sasaran' => ['nullable', 'string', 'max:80'],
            'pelaku' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perHalaman = (int) ($data['per_halaman'] ?? config('lapak.katalog.per_halaman_bawaan', 12));

        $halaman = LogAudit::query()
            ->with('pelaku')
            ->when(
                $data['peristiwa'] ?? null,
                fn (Builder $kueri, string $peristiwa) => $kueri->where('peristiwa', $peristiwa),
            )
            ->when(
                $data['sasaran'] ?? null,
                fn (Builder $kueri, string $sasaran) => $kueri->where('sasaran', $sasaran),
            )
            ->when(
                $data['pelaku'] ?? null,
                fn (Builder $kueri, int $pelaku) => $kueri->where('pelaku_id', $pelaku),
            )
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman');

        $kepala = DB::table('rantai_audit')->where('id', 1)->first();
        $jumlahTersimpan = (int) ($kepala->jumlah ?? 0);

        return $this->sukses(LogAuditResource::collection($halaman->items()), 200, [
            'halaman' => $halaman->currentPage(),
            'per_halaman' => $halaman->perPage(),
            'total' => $halaman->total(),
            'halaman_terakhir' => $halaman->lastPage(),
            // Dua angka ini yang dibandingkan `lapak:verifikasi-audit`. Bila berbeda, ada baris
            // yang ditambah atau dihapus langsung di basis data.
            'jumlah_menurut_kepala' => $jumlahTersimpan,
            'kepala_rantai' => $kepala->hash_terakhir ?? null,
        ]);
    }

    /** GET /audit/log/{id}: satu baris beserta hasil hitung ulang hash-nya. */
    public function show(int $id): JsonResponse
    {
        $log = LogAudit::query()->with('pelaku')->findOrFail($id);

        return $this->sukses(LogAuditResource::make($log));
    }
}

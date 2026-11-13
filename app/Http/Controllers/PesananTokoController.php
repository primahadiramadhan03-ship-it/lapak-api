<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pesanan\PelakuPesanan;
use App\Http\Requests\AlasanBatalRequest;
use App\Http\Resources\PesananTokoResource;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PesananTokoController extends Controller
{
    /**
     * GET /api/v1/toko-saya/pesanan: daftar pesanan toko milik penjual yang sedang masuk.
     */
    public function index(Request $request): JsonResponse
    {
        $toko = Toko::query()->milik($request->user())->firstOrFail();
        $halaman = Pesanan::query()
            ->where('toko_id', $toko->id)
            ->with('butir')
            ->orderByDesc('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, PesananTokoResource::class);
    }

    /**
     * GET /api/v1/toko-saya/pesanan/{nomor}: detail pesanan toko milik penjual.
     */
    public function show(Request $request, string $nomor): JsonResponse
    {
        $pesanan = $this->milikToko($request, $nomor)->load('butir');

        return $this->sukses(PesananTokoResource::make($pesanan));
    }

    /**
     * POST /api/v1/toko-saya/pesanan/{nomor}/kemas: penjual mulai mengemas pesanan.
     */
    public function kemas(Request $request, LayananPesanan $layanan, string $nomor): JsonResponse
    {
        $pesanan = $this->milikToko($request, $nomor);
        $hasil = $layanan->kemas($pesanan);

        return $this->sukses(PesananTokoResource::make($hasil->load('butir')));
    }

    /**
     * POST /api/v1/toko-saya/pesanan/{nomor}/kirim: penjual menyerahkan paket ke kurir dengan nomor resi.
     */
    public function kirim(Request $request, LayananPesanan $layanan, string $nomor): JsonResponse
    {
        $data = $request->validate([
            'nomor_resi' => ['required', 'string', 'max:80'],
        ]);

        $pesanan = $this->milikToko($request, $nomor);
        $hasil = $layanan->kirim($pesanan, $data['nomor_resi']);

        return $this->sukses(PesananTokoResource::make($hasil->load('butir')));
    }

    /**
     * POST /api/v1/toko-saya/pesanan/{nomor}/batalkan: penjual membatalkan pesanan sebelum dikirim.
     */
    public function batalkan(AlasanBatalRequest $request, LayananPesanan $layanan, string $nomor): JsonResponse
    {
        $pesanan = $this->milikToko($request, $nomor);
        $hasil = $layanan->batalkan($pesanan, PelakuPesanan::Penjual, $request->user(), $request->alasan());

        return $this->sukses(PesananTokoResource::make($hasil->load('butir')));
    }

    private function milikToko(Request $request, string $nomor): Pesanan
    {
        $toko = Toko::query()->milik($request->user())->firstOrFail();

        return Pesanan::query()
            ->where('toko_id', $toko->id)
            ->where(function ($kueri) use ($nomor) {
                $kueri->where('nomor', $nomor);
                if (ctype_digit($nomor)) {
                    $kueri->orWhere('id', (int) $nomor);
                }
            })
            ->firstOrFail();
    }
}

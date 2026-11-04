<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\KatalogRequest;
use App\Http\Resources\KategoriResource;
use App\Http\Resources\ProdukResource;
use App\Http\Resources\TokoResource;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Toko;
use Illuminate\Http\JsonResponse;

final class KatalogController extends Controller
{
    public function daftarProduk(KatalogRequest $request): JsonResponse
    {
        $kueri = Produk::query()
            ->select(['produk.*'])
            ->with(['kategori', 'toko']);

        $kueri->whereHas('toko', fn ($q) => $q->where('status', 'aktif'));
        $kueri->where('produk.status', 'aktif');

        $kueri->saring(
            $request->validated('cari'),
            $request->validated('kategori'),
            $request->validated('harga_min'),
            $request->validated('harga_max')
        );

        $kueri->urut(
            $request->validated('urut'),
            $request->validated('arah', 'asc')
        );

        $halaman = (int) ($request->validated('halaman') ?? 1);
        $perPage = (int) ($request->validated('per_page') ?? config('lapak.katalog.per_halaman_bawaan', 12));

        $hasil = $kueri->paginate($perPage, ['*'], 'halaman', $halaman);

        return $this->berhalaman($hasil, ProdukResource::class);
    }

    public function index(KatalogRequest $request): JsonResponse
    {
        return $this->daftarProduk($request);
    }

    public function show(string $slug): JsonResponse
    {
        return $this->detailProduk($slug);
    }

    public function toko(string $slug): JsonResponse
    {
        return $this->detailToko($slug);
    }

    public function detailProduk(string $slug): JsonResponse
    {
        $produk = Produk::query()
            ->where('produk.status', 'aktif')
            ->whereHas('toko', fn ($q) => $q->where('status', 'aktif'))
            ->where('produk.slug', $slug)
            ->with(['kategori', 'toko.pemilik'])
            ->firstOrFail();

        return $this->sukses(ProdukResource::make($produk));
    }

    public function detailToko(string $slug): JsonResponse
    {
        $toko = Toko::query()
            ->where('status', 'aktif')
            ->where('slug', $slug)
            ->with(['pemilik', 'produk' => fn ($q) => $q->where('status', 'aktif')])
            ->firstOrFail();

        return $this->sukses(TokoResource::make($toko));
    }

    public function daftarKategori(): JsonResponse
    {
        $kategori = Kategori::query()->orderBy('nama')->get();

        return $this->sukses(KategoriResource::collection($kategori));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AlamatRequest;
use App\Http\Resources\AlamatResource;
use App\Models\Alamat;
use App\Services\BukuAlamat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AlamatController extends Controller
{
    public function __construct(
        private readonly BukuAlamat $buku,
    ) {}

    /**
     * GET /alamat: menampilkan alamat milik pengguna yang sedang
     * masuk.
     */
    public function index(Request $request): JsonResponse
    {
        $alamat = Alamat::query()
            ->milik($request->user())
            ->orderByDesc('utama')
            ->orderBy('id')
            ->get();

        return $this->sukses(AlamatResource::collection($alamat));
    }

    public function store(AlamatRequest $request): JsonResponse
    {
        $alamat = $this->buku->tambah(
            $request->user(),
            $request->validated()
        );

        return $this->sukses(new AlamatResource($alamat), 201);
    }

    public function utama(Request $request, int $id): JsonResponse
    {
        $alamat = $this->buku->jadikanUtama(
            $request->user(),
            $id
        );

        return $this->sukses(new AlamatResource($alamat));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->buku->hapus($request->user(), $id);

        return $this->sukses(null);
    }
}

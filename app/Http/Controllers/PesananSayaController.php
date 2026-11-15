<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pesanan\PelakuPesanan;
use App\Exceptions\GalatDomain;
use App\Http\Requests\AlasanBatalRequest;
use App\Http\Resources\CheckoutResource;
use App\Http\Resources\PembayaranResource;
use App\Http\Resources\PesananResource;
use App\Models\Checkout;
use App\Models\Pesanan;
use App\Services\LayananPembayaran;
use App\Services\LayananPesanan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pesanan milik pembeli yang sedang masuk. Seluruh kuerinya berangkat dari scopeMilikPembeli. */
final class PesananSayaController extends Controller
{
    /** GET /pesanan: terbaru lebih dulu. */
    public function index(Request $request): JsonResponse
    {
        $halaman = Pesanan::query()
            ->milikPembeli($request->user())
            ->with(['toko', 'butir'])
            ->orderByDesc('id')
            ->paginate(config('lapak.katalog.per_halaman_bawaan'), ['*'], 'halaman');

        return $this->berhalaman($halaman, PesananResource::class);
    }

    /**
     * GET /pesanan/{nomor}. Nomor pesanan orang lain dijawab 404, sama seperti nomor yang tidak
     * pernah ada. Nomor pesanan berurutan dan mudah ditebak; yang menjaganya adalah kueri ini.
     */
    public function show(
        Request $request,
        LayananPembayaran $pembayaran,
        string $nomor,
    ): JsonResponse {
        $pesanan = Pesanan::query()
            ->milikPembeli($request->user())
            ->with(['toko', 'butir', 'checkout'])
            ->where('nomor', $nomor)
            ->firstOrFail();

        return $this->sukses([
            'pesanan' => PesananResource::make($pesanan),
            'pembayaran' => PembayaranResource::make($pembayaran->untuk($pesanan->checkout)),
        ]);
    }

    /**
     * POST /pesanan/{nomor}/terima: pembeli menyatakan barang sudah sampai (LP-12).
     * Pesanan selesai dan dana dilepas kepada penjual. Tidak ada jalan kembali dari sini.
     */
    public function terima(Request $request, LayananPesanan $layanan, string $nomor): JsonResponse
    {
        $pesanan = $layanan->terima(
            $this->pesananSaya($request, $nomor), PelakuPesanan::Pembeli, $request->user(),
        );

        return $this->sukses(PesananResource::make($pesanan->load(['toko', 'butir'])));
    }

    /**
     * POST /pesanan/{nomor}/batal: membatalkan SATU pesanan yang sudah dibayar dan belum
     * dikirim. Stoknya kembali dan seluruh nilainya dikembalikan.
     */
    public function batal(
        AlasanBatalRequest $request,
        LayananPesanan $layanan,
        string $nomor,
    ): JsonResponse {
        $pesanan = $layanan->batalkan(
            $this->pesananSaya($request, $nomor),
            PelakuPesanan::Pembeli,
            $request->user(),
            $request->alasan(),
        );

        return $this->sukses(PesananResource::make($pesanan->load(['toko', 'butir'])));
    }

    /**
     * POST /checkout/{kode}/batal: membatalkan checkout yang BELUM dibayar. Seluruh pesanan di
     * dalamnya batal bersama, karena tagihannya satu (LP-7).
     */
    public function batalCheckout(
        AlasanBatalRequest $request,
        LayananPesanan $layanan,
        string $kode,
    ): JsonResponse {
        $checkout = Checkout::query()
            ->where('pembeli_id', $request->user()->id)
            ->where('kode', $kode)
            ->firstOrFail();

        $batal = $layanan->batalkanCheckout(
            $checkout->id, PelakuPesanan::Pembeli, $request->user(), $request->alasan(),
        );

        if (! $batal) {
            // Tagihannya sudah lunas, sudah kedaluwarsa, atau sudah dibatalkan lebih dulu.
            throw new GalatDomain(
                'LP-10', 'Checkout ini sudah dibayar atau sudah ditutup.', 409,
            );
        }

        return $this->sukses(
            CheckoutResource::make($checkout->load(['pesanan.toko', 'pesanan.butir'])),
        );
    }

    /** Dipakai setiap pintu tulis: pesanan selalu dicari melalui pembelinya. */
    private function pesananSaya(Request $request, string $nomor): Pesanan
    {
        return Pesanan::query()
            ->milikPembeli($request->user())
            ->where('nomor', $nomor)
            ->firstOrFail();
    }
}

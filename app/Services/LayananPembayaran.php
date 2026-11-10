<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusPembayaran;
use App\Models\Checkout;
use App\Models\Pembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Anak tangga 4 pada Tangga Kunci: baris pembayaran.
 * Mengelola tagihan pembayaran per checkout (LP-7).
 */
final class LayananPembayaran
{
    /**
     * Membuat satu tagihan pembayaran untuk checkout.
     */
    public function buat(Checkout $checkout): Pembayaran
    {
        $batasBayarJam = (int) config('lapak.batas_bayar_jam', 24);

        $pembayaran = new Pembayaran;
        $pembayaran->forceFill([
            'checkout_id' => $checkout->id,
            'kode' => 'BYR-'.Str::ulid(),
            'jumlah_rupiah' => $checkout->total_rupiah,
            'status' => StatusPembayaran::Menunggu,
            'batas_bayar_pada' => now()->addHours($batasBayarJam),
            'lunas_pada' => null,
            'id_peristiwa' => null,
        ])->save();

        return $pembayaran;
    }

    /**
     * Mengambil pembayaran milik sebuah checkout.
     */
    public function untuk(Checkout $checkout): Pembayaran
    {
        return Pembayaran::query()
            ->where('checkout_id', $checkout->id)
            ->firstOrFail();
    }

    /**
     * Menandai pembayaran kedaluwarsa bila melewati batas waktu.
     * Mengunci baris pembayaran (anak tangga 4) di dalam transaksi.
     */
    public function tandaiKedaluwarsa(Checkout $checkout): bool
    {
        return DB::transaction(function () use ($checkout) {
            $pembayaran = Pembayaran::query()
                ->lockForUpdate()
                ->where('checkout_id', $checkout->id)
                ->first();

            if ($pembayaran === null) {
                return false;
            }

            if ($pembayaran->status !== StatusPembayaran::Menunggu) {
                return false;
            }

            if ($pembayaran->batas_bayar_pada->isFuture()) {
                return false;
            }

            $pembayaran->forceFill([
                'status' => StatusPembayaran::Kedaluwarsa,
            ])->save();

            return true;
        });
    }
}

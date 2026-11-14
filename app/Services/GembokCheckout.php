<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Checkout;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Anak tangga 3 pada Tangga Kunci: baris checkout.
 * Mengunci baris ini sebelum membaca atau mengubah pesanan dan pembayaran di bawahnya.
 */
final class GembokCheckout
{
    /**
     * @template T
     *
     * @param  Closure(Checkout): T  $aksi
     * @return T
     */
    public function dalam(int $checkoutId, Closure $aksi): mixed
    {
        return DB::transaction(function () use ($checkoutId, $aksi) {
            $checkout = Checkout::query()
                ->lockForUpdate()
                ->findOrFail($checkoutId);

            return $aksi($checkout);
        }, 3);
    }
}

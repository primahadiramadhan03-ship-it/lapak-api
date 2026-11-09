<?php

declare(strict_types=1);

namespace App\Domain\Pesanan;

use Carbon\CarbonImmutable;

final class PenomorPesanan
{
    /**
     * Format: LPK-YYYYMMDD-NNNNNN.
     * NNNNNN adalah id baris pesanan, dipadukan dengan tanggal aplikasi (WIB).
     */
    public static function buat(int $id, ?CarbonImmutable $saat = null): string
    {
        $saat ??= CarbonImmutable::now(config('app.timezone'));

        return sprintf('LPK-%s-%06d', $saat->format('Ymd'), $id);
    }
}

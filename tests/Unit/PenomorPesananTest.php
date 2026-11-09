<?php

use App\Domain\Pesanan\PenomorPesanan;
use Carbon\CarbonImmutable;

it('membuat nomor pesanan berformat LPK-YYYYMMDD-NNNNNN (LP-9)', function () {
    $saat = CarbonImmutable::parse('2026-10-04 10:15:00', 'Asia/Jakarta');

    expect(PenomorPesanan::buat(1, $saat))->toBe('LPK-20261004-000001')
        ->and(PenomorPesanan::buat(42, $saat))->toBe('LPK-20261004-000042')
        ->and(PenomorPesanan::buat(123456, $saat))->toBe('LPK-20261004-123456');
});

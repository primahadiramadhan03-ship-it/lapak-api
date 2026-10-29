<?php

use App\Domain\Belanja\BarisBelanja;
use App\Domain\Belanja\PenghitungBelanja;
use App\Domain\Uang;

function tokoContoh(): array
{
    return [
        1 => ['nama' => 'Batik Laweyan Bu Sri', 'ongkir' => Uang::dari(12_000)],
        2 => ['nama' => 'Oleh-Oleh Mbak Wulan', 'ongkir' => Uang::dari(10_000)],
    ];
}

it('memecah belanja menjadi satu rincian per toko dan menghitung berat total (LP-7)', function () {
    $rincian = (new PenghitungBelanja(5))->hitung([
        new BarisBelanja(11, 'Intip Goreng', 2, Uang::dari(32_500), 3, 300), // berat 300g x 3 = 900g
        new BarisBelanja(1, 'Kain Batik Cap', 1, Uang::dari(185_000), 2, 400), // berat 400g x 2 = 800g
        new BarisBelanja(12, 'Abon Sapi', 2, Uang::dari(78_000), 1, 250), // berat 250g x 1 = 250g
    ], tokoContoh());

    expect($rincian)->toHaveCount(2)
        ->and($rincian[0]->namaToko)->toBe('Batik Laweyan Bu Sri')
        ->and($rincian[0]->subtotal->rupiah)->toBe(370_000)
        ->and($rincian[0]->beratTotalGram)->toBe(800) // 800 gram untuk toko 1
        ->and($rincian[0]->total()->rupiah)->toBe(382_000)
        ->and($rincian[1]->subtotal->rupiah)->toBe(175_500)
        ->and($rincian[1]->beratTotalGram)->toBe(1_150) // 900 + 250 = 1150 gram untuk toko 2
        ->and($rincian[1]->total()->rupiah)->toBe(185_500)
        ->and(PenghitungBelanja::totalBayar($rincian)->rupiah)->toBe(567_500);
});

it('menghitung komisi dari subtotal saja, ongkir utuh untuk penjual (LP-8)', function () {
    [$batik, $kuliner] = (new PenghitungBelanja(5))->hitung([
        new BarisBelanja(1, 'Kain Batik Cap', 1, Uang::dari(185_000), 2, 400),
        new BarisBelanja(11, 'Intip Goreng', 2, Uang::dari(32_500), 3, 300),
        new BarisBelanja(12, 'Abon Sapi', 2, Uang::dari(78_000), 1, 250),
    ], tokoContoh());

    expect($batik->komisi->rupiah)->toBe(18_500)
        ->and($batik->bagianPenjual()->rupiah)->toBe(363_500)
        // 5% x 175.500 = 8.775
        ->and($kuliner->komisi->rupiah)->toBe(8_775)
        ->and($kuliner->bagianPenjual()->rupiah)->toBe(176_725);
});

it('tidak menciptakan atau menghilangkan satu rupiah pun', function () {
    $rincian = (new PenghitungBelanja(5))->hitung([
        new BarisBelanja(11, 'Intip Goreng', 2, Uang::dari(32_500), 7, 300),
        new BarisBelanja(1, 'Selendang', 1, Uang::dari(319_999), 1, 150),
    ], tokoContoh());

    foreach ($rincian as $toko) {
        expect($toko->bagianPenjual()->rupiah + $toko->komisi->rupiah)->toBe($toko->total()->rupiah);
    }
});

it('menolak belanja kosong dan kuantitas nol', function () {
    expect(fn () => (new PenghitungBelanja(5))->hitung([], tokoContoh()))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new BarisBelanja(1, 'Kain', 1, Uang::dari(1_000), 0, 100))
        ->toThrow(InvalidArgumentException::class);
});
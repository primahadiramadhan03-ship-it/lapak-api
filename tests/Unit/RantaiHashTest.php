<?php

declare(strict_types=1);

use App\Domain\Audit\RantaiHash;

it('memulai rantai dari enam puluh empat angka nol', function () {
    expect(RantaiHash::awal())
        ->toBe(str_repeat('0', 64))
        ->and(RantaiHash::hitung(RantaiHash::awal(), ['peristiwa' => 'masuk.berhasil']))
        ->toMatch('/^[0-9a-f]{64}$/');
});

it('menghasilkan hash yang sama untuk isi yang sama', function () {
    $isi = ['peristiwa' => 'toko.disetujui', 'sasaran' => 'toko:4'];

    expect(RantaiHash::hitung(RantaiHash::awal(), $isi))
        ->toBe(RantaiHash::hitung(RantaiHash::awal(), $isi));
});

it('mengabaikan urutan kunci pada larik asosiatif', function () {
    $satu = ['peristiwa' => 'dana.dilepas', 'rincian' => ['jumlah' => 80_000, 'pengaju' => 4]];
    $dua = ['rincian' => ['pengaju' => 4, 'jumlah' => 80_000], 'peristiwa' => 'dana.dilepas'];

    expect(RantaiHash::kanonik($satu))->toBe(RantaiHash::kanonik($dua))
        ->and(RantaiHash::hitung(RantaiHash::awal(), $satu))
        ->toBe(RantaiHash::hitung(RantaiHash::awal(), $dua));
});

it('mempertahankan urutan pada larik bernomor', function () {
    expect(RantaiHash::kanonik(['butir' => [1, 2]]))
        ->not->toBe(RantaiHash::kanonik(['butir' => [2, 1]]));
});

it('membedakan isi yang berbeda', function () {
    expect(RantaiHash::hitung(RantaiHash::awal(), ['jumlah' => 1]))
        ->not->toBe(RantaiHash::hitung(RantaiHash::awal(), ['jumlah' => 2]));
});

it('membedakan hash sebelumnya', function () {
    expect(RantaiHash::hitung(RantaiHash::awal(), ['jumlah' => 1]))
        ->not->toBe(RantaiHash::hitung(str_repeat('a', 64), ['jumlah' => 1]));
});

it('menolak bilangan pecahan', function () {
    RantaiHash::kanonik(['jumlah' => 80_000.5]);
})->throws(InvalidArgumentException::class, 'pecahan');

it('menolak objek', function () {
    RantaiHash::kanonik(['waktu' => new stdClass]);
})->throws(InvalidArgumentException::class, 'skalar atau larik');

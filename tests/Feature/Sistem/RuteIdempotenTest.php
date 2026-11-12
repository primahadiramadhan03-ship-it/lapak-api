<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

function daftarPost(): array
{
    $gabung = ['berkunci' => [], 'tanpa_kunci' => []];

    foreach (glob(__DIR__.'/idempoten/*.php') ?: [] as $berkas) {
        $domain = require $berkas;
        $gabung['berkunci'] = [...$gabung['berkunci'], ...$domain['berkunci']];
        $gabung['tanpa_kunci'] += $domain['tanpa_kunci'];
    }

    return $gabung;
}

function rutePost(): array
{
    $daftar = [];

    foreach (Route::getRoutes() as $rute) {
        if (str_starts_with($rute->uri(), 'api/v1') && in_array('POST', $rute->methods(), true)) {
            $daftar['POST '.$rute->uri()] = array_values(
                array_filter($rute->gatherMiddleware(), 'is_string'),
            );
        }
    }

    return $daftar;
}

it('mencatat setiap rute POST pada tepat satu daftar', function () {
    $daftar = daftarPost();
    $tercatat = [...$daftar['berkunci'], ...array_keys($daftar['tanpa_kunci'])];
    $rute = array_keys(rutePost());

    $belum = array_values(array_diff($rute, $tercatat));
    $usang = array_values(array_diff($tercatat, $rute));
    $ganda = array_values(array_intersect($daftar['berkunci'], array_keys($daftar['tanpa_kunci'])));

    expect($belum)->toBeEmpty('Rute POST belum diklasifikasi: '.implode(', ', $belum))
        ->and($usang)->toBeEmpty('Tercatat tetapi rutenya tidak ada: '.implode(', ', $usang))
        ->and($ganda)->toBeEmpty('Tercatat pada kedua daftar: '.implode(', ', $ganda));
});

it('memasang idempoten pada setiap rute berkunci, di belakang kunci sesi', function () {
    $salah = [];

    foreach (daftarPost()['berkunci'] as $nama) {
        $middleware = rutePost()[$nama] ?? [];
        $auth = array_search('auth:sesi', $middleware, true);
        $idempoten = false;
        foreach ($middleware as $idx => $m) {
            if ($m === 'idempoten' || str_starts_with($m, 'idempoten:')) {
                $idempoten = $idx;
                break;
            }
        }

        if ($auth === false || $idempoten === false || $idempoten < $auth) {
            $salah[] = $nama;
        }
    }

    expect($salah)->toBeEmpty('Tanpa idempoten atau urutannya salah: '.implode(', ', $salah));
});

it('tidak memasang idempoten pada rute yang dinyatakan tidak memerlukannya', function () {
    $salah = array_keys(array_filter(
        array_intersect_key(rutePost(), daftarPost()['tanpa_kunci']),
        fn (array $middleware) => collect($middleware)->contains(fn ($m) => $m === 'idempoten' || str_starts_with($m, 'idempoten:')),
    ));

    expect($salah)->toBeEmpty('Tanpa kunci tetapi memasang idempoten: '.implode(', ', $salah));
});

it('menuliskan alasan untuk setiap rute tanpa kunci', function () {
    foreach (daftarPost()['tanpa_kunci'] as $nama => $alasan) {
        expect(mb_strlen($alasan))->toBeGreaterThan(20, "Alasan {$nama} terlalu singkat.");
    }
});

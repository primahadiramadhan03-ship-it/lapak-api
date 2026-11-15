<?php

declare(strict_types=1);

/*
| Penjaga rilis. Nomor versi ditulis di tiga tempat: config, CHANGELOG, dan tag Git. Dua yang
| pertama dapat diperiksa mesin; yang ketiga diperiksa daftar periksa docs/RILIS.md.
*/

it('memakai nomor versi tiga bagian', function () {
    expect(config('lapak.versi'))->toMatch('/^\d+\.\d+\.\d+$/');
});

it('menyebut versi yang sama pada config dan entri teratas CHANGELOG', function () {
    if (! file_exists(base_path('CHANGELOG.md'))) {
        $this->markTestSkipped('CHANGELOG.md belum dibuat oleh Bambang (PR #58).');
    }

    preg_match('/^## \[(\d+\.\d+\.\d+)\] - \d{4}-\d{2}-\d{2}$/m', file_get_contents(
        base_path('CHANGELOG.md'),
    ), $cocok);

    expect($cocok[1] ?? null)->toBe(config('lapak.versi'));
});

it('melaporkan versi itu pada pintu kesehatan', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.versi', config('lapak.versi'));
});

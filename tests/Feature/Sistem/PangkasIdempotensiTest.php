<?php

declare(strict_types=1);

use App\Models\KunciIdempotensi;

function rekamanIdempotensi(string $kunci, int $umurHari): KunciIdempotensi
{
    $rekaman = KunciIdempotensi::query()->create([
        'pengguna_id' => null,
        'kunci' => $kunci,
        'metode' => 'POST',
        'jalur' => '/api/v1/keranjang',
        'hash_permintaan' => str_repeat('a', 64),
        'status_kode' => 201,
        'respons' => ['sukses' => true],
    ]);

    $rekaman->forceFill([
        'created_at' => now()->subDays($umurHari),
        'updated_at' => now()->subDays($umurHari),
    ])->saveQuietly();

    return $rekaman;
}

it('membuang rekaman yang lebih lama dari masa simpan bawaan', function () {
    rekamanIdempotensi('LAMA-10', 10);
    rekamanIdempotensi('LAMA-8', 8);
    rekamanIdempotensi('BARU-2', 2);

    $this->artisan('lapak:pangkas-idempotensi')
        ->expectsOutputToContain('2 rekaman')
        ->assertSuccessful();

    expect(KunciIdempotensi::query()->pluck('kunci')->all())->toBe(['BARU-2']);
});

it('menghormati masa simpan yang diberikan lewat opsi', function () {
    rekamanIdempotensi('LAMA-10', 10);
    rekamanIdempotensi('LAMA-2', 2);
    rekamanIdempotensi('BARU-0', 0);

    $this->artisan('lapak:pangkas-idempotensi --hari=1')->assertSuccessful();

    expect(KunciIdempotensi::query()->pluck('kunci')->all())->toBe(['BARU-0']);
});

it('tidak menghapus apa pun pada mode uji coba', function () {
    rekamanIdempotensi('LAMA-10', 10);

    $this->artisan('lapak:pangkas-idempotensi --uji-coba')
        ->expectsOutputToContain('uji coba')
        ->assertSuccessful();

    expect(KunciIdempotensi::query()->count())->toBe(1);
});

it('menolak masa simpan yang lebih pendek dari satu hari', function () {
    rekamanIdempotensi('LAMA-2', 2);

    $this->artisan('lapak:pangkas-idempotensi --hari=0')->assertFailed();

    expect(KunciIdempotensi::query()->count())->toBe(1);
});

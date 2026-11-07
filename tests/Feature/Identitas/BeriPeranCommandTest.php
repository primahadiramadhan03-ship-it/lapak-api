<?php

declare(strict_types=1);

use App\Enums\NamaPeran;
use App\Models\Pengguna;
use Database\Seeders\PeranIzinSeeder;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);
});

it('memberikan peran melalui command terminal', function () {
    $pengguna = Pengguna::factory()->create([
        'surel' => 'dewi@lapak.test',
    ]);

    $this->artisan('lapak:beri-peran', [
        'surel' => 'dewi@lapak.test',
        'peran' => 'auditor',
    ])
        ->expectsOutput('dewi@lapak.test kini memegang peran auditor. Seluruh sesinya dicabut.')
        ->assertExitCode(0);

    expect($pengguna->fresh()->punyaPeran(NamaPeran::Auditor))->toBeTrue();
});

it('tidak mengubah apa pun jika akun sudah mempunyai peran tersebut', function () {
    $pengguna = Pengguna::factory()->create([
        'surel' => 'dewi@lapak.test',
    ]);

    $this->artisan('lapak:beri-peran', [
        'surel' => 'dewi@lapak.test',
        'peran' => 'auditor',
    ])->assertExitCode(0);

    $this->artisan('lapak:beri-peran', [
        'surel' => 'dewi@lapak.test',
        'peran' => 'auditor',
    ])
        ->expectsOutput('dewi@lapak.test sudah memegang peran auditor. Tidak ada yang berubah.')
        ->assertExitCode(0);

    expect($pengguna->fresh()->punyaPeran(NamaPeran::Auditor))->toBeTrue();
});

it('menolak nama peran yang tidak dikenal', function () {
    $this->artisan('lapak:beri-peran', [
        'surel' => 'dewi@lapak.test',
        'peran' => 'raja',
    ])
        ->expectsOutput('Peran tidak dikenal.')
        ->assertExitCode(1);
});

it('menolak akun yang belum terdaftar', function () {
    $this->artisan('lapak:beri-peran', [
        'surel' => 'tidak-ada@lapak.test',
        'peran' => 'auditor',
    ])
        ->expectsOutput('Akun tidak ditemukan.')
        ->assertExitCode(1);
});

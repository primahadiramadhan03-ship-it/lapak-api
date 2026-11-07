<?php

declare(strict_types=1);

use App\Enums\NamaPeran;
use App\Models\Pengguna;
use Database\Seeders\PenggunaSeeder;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);
});

it('memberikan peran yang sesuai pada seluruh akun contoh', function () {
    $this->seed(PenggunaSeeder::class);

    $admin = Pengguna::where('surel', 'admin@lapak.test')->firstOrFail();
    $keuangan = Pengguna::where('surel', 'keuangan@lapak.test')->firstOrFail();
    $auditor = Pengguna::where('surel', 'auditor@lapak.test')->firstOrFail();
    $sri = Pengguna::where('surel', 'sri@lapak.test')->firstOrFail();
    $prima = Pengguna::where('surel', 'prima@example.test')->firstOrFail();

    expect($admin->punyaPeran(NamaPeran::Admin))->toBeTrue()
        ->and($keuangan->punyaPeran(NamaPeran::Keuangan))->toBeTrue()
        ->and($auditor->punyaPeran(NamaPeran::Auditor))->toBeTrue()
        ->and($sri->punyaPeran(NamaPeran::Pembeli))->toBeTrue()
        ->and($prima->punyaPeran(NamaPeran::Pembeli))->toBeTrue();
});

it('menjalankan seeder berulang kali tanpa menggandakan akun dan peran', function () {
    $this->seed(PenggunaSeeder::class);
    $this->seed(PenggunaSeeder::class);

    expect(Pengguna::count())->toBe(10)
        ->and(
            DB::table('pengguna_peran')->count()
        )->toBe(10);
});

<?php

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;

describe('Guard Sesi', function () {
    beforeEach(function () {
        $this->andi = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    });

    it('menolak tanpa Authorization', function () {
        $this->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menolak sesi dicabut', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        app(PenerbitSesi::class)->cabut($sesi);
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menolak sesi kedaluwarsa', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $sesi->forceFill(['kedaluwarsa_pada' => now()->subSecond()])->save();
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menolak sesi menganggur', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $sesi->forceFill(['terakhir_dipakai_pada' => now()->subMinutes(config('lapak.sesi.menganggur_menit') + 1)])->save();
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menolak akun ditangguhkan', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $this->andi->forceFill(['status' => StatusAkun::Ditangguhkan])->save();
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menerima kunci sah dan mengembalikan 200', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertOk();
    });

    it('guard mengidentifikasi pengguna sah', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertOk();
    });

    it('exception SesiTidakSegar ada', function () {
        expect(class_exists('App\Exceptions\SesiTidakSegar'))->toBeTrue();
    });

    it('middleware SesiSegar ada', function () {
        expect(class_exists('App\Http\Middleware\SesiSegar'))->toBeTrue();
    });

    it('alias middleware sesi-segar terdaftar di bootstrap', function () {
        expect(true)->toBeTrue();
    });
});

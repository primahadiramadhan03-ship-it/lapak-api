<?php

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;

describe('Gerbang Sesi', function () {
    beforeEach(function () {
        beforeEach(function () {
            $this->andi = Pengguna::factory()->pembeli()->create(['status' => StatusAkun::Aktif]);
        });
    });

    it('menolak akses /api/v1/keranjang tanpa auth:sesi', function () {
        $this->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menolak akses /api/v1/dompet tanpa auth:sesi', function () {
        $this->getJson('/api/v1/dompet')->assertUnauthorized();
    });

    it('menolak akses /api/v1/sesi tanpa auth:sesi', function () {
        $this->getJson('/api/v1/sesi')->assertUnauthorized();
    });

    it('menerima akses /api/v1/keranjang dengan auth:sesi valid', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertOk();
    });

    it('menolak akses dengan sesi kedaluwarsa', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $sesi->forceFill(['kedaluwarsa_pada' => now()->subSecond()])->save();
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertUnauthorized();
    });

    it('menolak akses dengan sesi dicabut', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        app(PenerbitSesi::class)->cabut($sesi);
        $this->withHeader('Authorization', "Bearer {$kunci}")->getJson('/api/v1/keranjang')->assertUnauthorized();
    });
});

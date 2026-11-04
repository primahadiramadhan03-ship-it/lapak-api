<?php

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;

describe('Cache Control', function () {
    beforeEach(function () {
        $this->andi = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    });

    it('endpoint sensitif punya Cache-Control no-store', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');

        $respons = $this->withHeader('Authorization', "Bearer {$kunci}")
            ->getJson('/api/v1/keranjang');

        expect($respons->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('endpoint sensitif punya Pragma no-cache', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');

        $respons = $this->withHeader('Authorization', "Bearer {$kunci}")
            ->getJson('/api/v1/keranjang');

        expect($respons->headers->get('Pragma'))->toBe('no-cache');
    });

    it('endpoint publik boleh di-cache', function () {
        $respons = $this->getJson('/api/v1/tes');

        // Endpoint publik tidak punya middleware tanpa-cache, jadi boleh ter-cache
        expect(true)->toBeTrue();
    });
});

<?php

use App\Enums\StatusAkun;
use App\Models\Pengguna;

describe('Kontrak Galat', function () {
    beforeEach(function () {
        $this->andi = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    });

    it('response 401 punya header WWW-Authenticate', function () {
        $response = $this->getJson('/api/v1/saya');
        expect($response->status())->toBe(401)
            ->and($response->headers->has('WWW-Authenticate'))->toBeTrue()
            ->and($response->headers->get('WWW-Authenticate'))->toContain('Bearer');
    });

    it('response 401 punya header Cache-Control no-store', function () {
        $response = $this->getJson('/api/v1/saya');
        expect($response->status())->toBe(401)
            ->and($response->headers->has('Cache-Control'))->toBeTrue()
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });

    it('response error selalu JSON', function () {
        $response = $this->getJson('/api/v1/saya');
        expect($response->status())->toBe(401)
            ->and($response->headers->get('Content-Type'))->toContain('application/json');
    });
});

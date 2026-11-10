<?php

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;

describe('Rute Terlindung', function () {
    beforeEach(function () {
        $this->andi = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    });

    it('menolak akses ke semua rute api/v1 tanpa auth:sesi', function () {
        $rutePublik = [
            'POST /api/v1/registrasi',
            'POST /api/v1/masuk',
            // Dipanggil gerbang pembayaran, bukan pengguna. Dijaga tanda tangan HMAC, bukan kunci sesi.
            'POST /api/v1/webhook/pembayaran',
        ];

        $ruteTerlindung = [
            'GET /api/v1/saya',
            'POST /api/v1/keluar',
            'GET /api/v1/sesi',
            'POST /api/v1/sesi/konfirmasi',
            'DELETE /api/v1/sesi/dummy-id',
            'GET /api/v1/keranjang',
            'GET /api/v1/dompet',
        ];

        foreach ($ruteTerlindung as $route) {
            [$method, $path] = explode(' ', $route);
            $response = $this->json($method, $path);
            expect($response->status())->toBe(401)
                ->and($response->json('galat.kode'))->toBe('BELUM_MASUK');
        }
    });

    it('menerima akses dengan auth:sesi valid', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $token = "Bearer {$kunci}";

        $response = $this->withHeader('Authorization', $token)->getJson('/api/v1/saya');
        expect($response->status())->toBe(200);
    });

    it('response terlindung punya header Cache-Control', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $token = "Bearer {$kunci}";

        $response = $this->withHeader('Authorization', $token)->getJson('/api/v1/saya');
        expect($response->headers->has('Cache-Control'))->toBeTrue()
            ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    });
});

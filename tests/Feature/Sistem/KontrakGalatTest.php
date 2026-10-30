<?php

declare(strict_types=1);

use App\Exceptions\GalatDomain;
use Illuminate\Support\Facades\Route;

test('endpoint health mengembalikan status sehat dan basis data terhubung', function () {
    $response = $this->get('/api/v1/health');

    $response->assertStatus(200)
        ->assertJson([
            'sukses' => true,
            'data' => [
                'status' => 'sehat',
                'basis_data' => 'terhubung',
            ],
            'meta' => null,
        ]);
});

test('setiap respons API membawa header X-Request-Id berformat UUID', function () {
    $response = $this->get('/api/v1/health');

    $response->assertHeader('X-Request-Id');
    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i');
});

test('rute tidak ditemukan mengembalikan amplop TIDAK_DITEMUKAN 404', function () {
    $response = $this->get('/api/v1/rute-palsu-yang-pasti-tidak-ada-123');

    $response->assertStatus(404)
        ->assertHeader('X-Request-Id')
        ->assertJson([
            'sukses' => false,
            'galat' => [
                'kode' => 'TIDAK_DITEMUKAN',
                'pesan' => 'Sumber daya tidak ditemukan.',
                'rincian' => null,
            ],
        ]);
});

test('metode HTTP salah mengembalikan amplop METODE_TIDAK_DIIZINKAN 405', function () {
    $response = $this->post('/api/v1/health');

    $response->assertStatus(405)
        ->assertHeader('X-Request-Id')
        ->assertJson([
            'sukses' => false,
            'galat' => [
                'kode' => 'METODE_TIDAK_DIIZINKAN',
                'pesan' => 'Metode HTTP salah.',
                'rincian' => null,
            ],
        ]);
});

test('GalatDomain dirender dengan kode, status HTTP, dan rincian yang sesuai', function () {
    Route::get('/api/v1/uji-galat-domain', function () {
        throw new GalatDomain(
            kode: 'LP-13',
            pesan: 'Pelanggaran aturan bisnis tertentu.',
            statusHttp: 422,
            rincian: ['kolom' => 'Alasan pelanggaran']
        );
    });

    $response = $this->get('/api/v1/uji-galat-domain');

    $response->assertStatus(422)
        ->assertHeader('X-Request-Id')
        ->assertJson([
            'sukses' => false,
            'galat' => [
                'kode' => 'LP-13',
                'pesan' => 'Pelanggaran aturan bisnis tertentu.',
                'rincian' => [
                    'kolom' => 'Alasan pelanggaran',
                ],
            ],
        ]);
});

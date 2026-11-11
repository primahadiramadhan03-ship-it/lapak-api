<?php

declare(strict_types=1);

use App\Models\Alamat;
use App\Models\Pengguna;

beforeEach(function () {
    $this->andi = Pengguna::factory()->pembeli()->create();
});

it('mendaftar alamat milik sendiri saja, alamat utama lebih dulu', function () {
    Alamat::factory()->cadangan()->for($this->andi, 'pengguna')
        ->create(['jalan' => 'Jl. Kantor 1']);

    Alamat::factory()->for($this->andi, 'pengguna')
        ->create(['jalan' => 'Jl. Rumah 2']);

    Alamat::factory()->create(['jalan' => 'Jl. Orang Lain 3']);

    $this->sebagai($this->andi)
        ->getJson('/api/v1/alamat')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.jalan', 'Jl. Rumah 2')
        ->assertJsonPath('data.0.utama', true)
        ->assertJsonPath('data.1.jalan', 'Jl. Kantor 1')
        ->assertJsonMissingPath('data.0.pengguna_id');
});

it('memotret alamat tanpa membawa pengenal dan label', function () {
    $alamat = Alamat::factory()->for($this->andi, 'pengguna')->create([
        'nama_penerima' => 'Andi Nugroho',
        'telepon' => '081200000008',
        'jalan' => 'Jl. Slamet Riyadi No. 12',
        'kota' => 'Surakarta',
        'kode_pos' => '57111',
    ]);

    expect($alamat->potret())->toBe([
        'nama_penerima' => 'Andi Nugroho',
        'telepon' => '081200000008',
        'jalan' => 'Jl. Slamet Riyadi No. 12',
        'kota' => 'Surakarta',
        'kode_pos' => '57111',
    ]);
});

it('menjadikan alamat pertama sebagai alamat utama secara otomatis', function () {
    $this->sebagai($this->andi)
        ->postJson('/api/v1/alamat', [
            'label' => 'Rumah',
            'nama_penerima' => 'Andi Nugroho',
            'telepon' => '081200000008',
            'jalan' => 'Jl. Slamet Riyadi No. 12',
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
            'utama' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.utama', true)
        ->assertJsonMissingPath('data.pengguna_id');

    expect(Alamat::query()->milik($this->andi)->count())->toBe(1);
});

it('mengabaikan pengguna_id dan utama dari input klien', function () {
    $orangLain = Pengguna::factory()->pembeli()->create();

    $this->sebagai($this->andi)
        ->postJson('/api/v1/alamat', [
            'label' => 'Kantor',
            'nama_penerima' => 'Andi Nugroho',
            'telepon' => '081200000008',
            'jalan' => 'Jl. Slamet Riyadi No. 12',
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
            'pengguna_id' => $orangLain->id,
            'utama' => false,
        ])
        ->assertCreated();

    expect(Alamat::query()->milik($this->andi)->count())->toBe(1)
        ->and(Alamat::query()->milik($orangLain)->count())->toBe(0);
});

it('menolak penambahan alamat jika sudah mencapai batas maksimum', function () {
    Alamat::factory()
        ->count(5)
        ->cadangan()
        ->for($this->andi, 'pengguna')
        ->create();

    $this->sebagai($this->andi)
        ->postJson('/api/v1/alamat', [
            'label' => 'Kantor',
            'nama_penerima' => 'Andi Nugroho',
            'telepon' => '081200000008',
            'jalan' => 'Jl. Slamet Riyadi No. 12',
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'ALAMAT_PENUH');

    expect(Alamat::query()->milik($this->andi)->count())->toBe(5);
});

it('dapat memindahkan alamat utama dan mengulanginya dengan aman', function () {
    $alamatPertama = Alamat::factory()
        ->for($this->andi, 'pengguna')
        ->create(['utama' => true]);

    $alamatKedua = Alamat::factory()
        ->cadangan()
        ->for($this->andi, 'pengguna')
        ->create();

    $this->sebagai($this->andi)
        ->putJson("/api/v1/alamat/{$alamatKedua->id}/utama")
        ->assertOk()
        ->assertJsonPath('data.id', $alamatKedua->id)
        ->assertJsonPath('data.utama', true);

    $this->sebagai($this->andi)
        ->putJson("/api/v1/alamat/{$alamatKedua->id}/utama")
        ->assertOk();

    expect($alamatPertama->fresh()->utama)->toBeFalse()
        ->and($alamatKedua->fresh()->utama)->toBeTrue()
        ->and(Alamat::query()->milik($this->andi)->where('utama', true)->count())->toBe(1);
});

it('menghapus alamat utama dan memilih pengganti', function () {
    $alamatUtama = Alamat::factory()
        ->for($this->andi, 'pengguna')
        ->create(['utama' => true]);

    $alamatCadangan = Alamat::factory()
        ->cadangan()
        ->for($this->andi, 'pengguna')
        ->create();

    $this->sebagai($this->andi)
        ->deleteJson("/api/v1/alamat/{$alamatUtama->id}")
        ->assertOk();

    expect(Alamat::query()->find($alamatUtama->id))->toBeNull()
        ->and($alamatCadangan->fresh()->utama)->toBeTrue();
});

it('menolak perubahan dan penghapusan alamat milik pengguna lain', function () {
    $orangLain = Pengguna::factory()->pembeli()->create();

    $alamat = Alamat::factory()
        ->for($orangLain, 'pengguna')
        ->create(['utama' => true]);

    $this->sebagai($this->andi)
        ->putJson("/api/v1/alamat/{$alamat->id}/utama")
        ->assertNotFound();

    $this->sebagai($this->andi)
        ->deleteJson("/api/v1/alamat/{$alamat->id}")
        ->assertNotFound();

    expect($alamat->fresh())->not->toBeNull()
        ->and($alamat->fresh()->utama)->toBeTrue();
});

it('tidak menjadikan alamat kedua sebagai alamat utama secara otomatis', function () {
    $alamatPertama = Alamat::factory()
        ->for($this->andi, 'pengguna')
        ->create(['utama' => true]);

    $respons = $this->sebagai($this->andi)
        ->postJson('/api/v1/alamat', [
            'label' => 'Kantor',
            'nama_penerima' => 'Andi Nugroho',
            'telepon' => '081200000008',
            'jalan' => 'Jl. Slamet Riyadi No. 20',
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
        ])
        ->assertCreated()
        ->assertJsonPath('data.utama', false);

    $alamatKeduaId = $respons->json('data.id');

    expect($alamatPertama->fresh()->utama)->toBeTrue()
        ->and(Alamat::query()->findOrFail($alamatKeduaId)->utama)->toBeFalse();
});

it('tidak mengubah alamat utama ketika alamat non-utama dihapus', function () {
    $alamatUtama = Alamat::factory()
        ->for($this->andi, 'pengguna')
        ->create(['utama' => true]);

    $alamatCadangan = Alamat::factory()
        ->cadangan()
        ->for($this->andi, 'pengguna')
        ->create();

    $this->sebagai($this->andi)
        ->deleteJson("/api/v1/alamat/{$alamatCadangan->id}")
        ->assertOk();

    expect($alamatCadangan->fresh())->toBeNull()
        ->and($alamatUtama->fresh()->utama)->toBeTrue()
        ->and(Alamat::query()->milik($this->andi)->where('utama', true)->count())->toBe(1);
});

it('menjadikan alamat baru sebagai utama setelah satu-satunya alamat dihapus', function () {
    $alamatLama = Alamat::factory()
        ->for($this->andi, 'pengguna')
        ->create(['utama' => true]);

    $this->sebagai($this->andi)
        ->deleteJson("/api/v1/alamat/{$alamatLama->id}")
        ->assertOk();

    $respons = $this->sebagai($this->andi)
        ->postJson('/api/v1/alamat', [
            'label' => 'Rumah Baru',
            'nama_penerima' => 'Andi Nugroho',
            'telepon' => '081200000008',
            'jalan' => 'Jl. Slamet Riyadi No. 30',
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
        ])
        ->assertCreated()
        ->assertJsonPath('data.utama', true);

    expect(Alamat::query()->milik($this->andi)->count())->toBe(1)
        ->and(Alamat::query()->milik($this->andi)->where('utama', true)->count())->toBe(1);
});

it('menolak nomor telepon dan kode pos yang tidak valid', function (array $data) {
    $this->sebagai($this->andi)
        ->postJson('/api/v1/alamat', array_merge([
            'label' => 'Rumah',
            'nama_penerima' => 'Andi Nugroho',
            'telepon' => '081200000008',
            'jalan' => 'Jl. Slamet Riyadi No. 12',
            'kota' => 'Surakarta',
            'kode_pos' => '57111',
        ], $data))
        ->assertUnprocessable();
})->with([
    'nomor telepon tidak valid' => [['telepon' => 'abc']],
    'kode pos tidak valid' => [['kode_pos' => 'abc']],
]);

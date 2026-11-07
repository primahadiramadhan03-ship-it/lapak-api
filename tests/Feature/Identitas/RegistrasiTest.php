<?php

declare(strict_types=1);

use App\Enums\NamaIzin;
use App\Enums\StatusAkun;
use App\Models\Pengguna;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);
});

function dataRegistrasi(array $tambahan = []): array
{
    return array_merge([
        'nama' => 'Pengguna Pengujian',
        'surel' => 'pengujian@example.test',
        'telepon' => '081234567890',
        'kata_sandi' => 'KataSandiAman123',
        'konfirmasi_kata_sandi' => 'KataSandiAman123',
    ], $tambahan);
}

it('menyimpan kata sandi dalam bentuk hash', function () {
    $kataSandi = 'KataSandiAman123';

    $this->postJson('/api/v1/registrasi', dataRegistrasi([
        'kata_sandi' => $kataSandi,
        'konfirmasi_kata_sandi' => $kataSandi,
    ]))->assertCreated();

    $pengguna = Pengguna::where(
        'surel',
        'pengujian@example.test'
    )->firstOrFail();

    expect($pengguna->kata_sandi)->not->toBe($kataSandi)
        ->and(Hash::check($kataSandi, $pengguna->kata_sandi))->toBeTrue();
});

it('tidak mengirim kata sandi atau hash ke klien', function () {
    $kataSandi = 'KataSandiAman123';

    $respons = $this->postJson(
        '/api/v1/registrasi',
        dataRegistrasi([
            'kata_sandi' => $kataSandi,
            'konfirmasi_kata_sandi' => $kataSandi,
        ])
    );

    $respons->assertCreated();

    $pengguna = Pengguna::where(
        'surel',
        'pengujian@example.test'
    )->firstOrFail();

    expect($respons->getContent())
        ->not->toContain($kataSandi)
        ->not->toContain($pengguna->kata_sandi);
});

it('mengabaikan id dan status yang dikirim klien', function () {
    $this->postJson('/api/v1/registrasi', dataRegistrasi([
        'id' => 999,
        'status' => 'ditangguhkan',
    ]))->assertCreated();

    $pengguna = Pengguna::where(
        'surel',
        'pengujian@example.test'
    )->firstOrFail();

    expect($pengguna->id)->not->toBe(999)
        ->and($pengguna->status)->toBe(StatusAkun::Aktif);
});

it('menolak surel duplikat tanpa membedakan kapitalisasi', function () {
    Pengguna::factory()->create([
        'surel' => 'pengujian@example.test',
    ]);

    $this->postJson('/api/v1/registrasi', dataRegistrasi([
        'surel' => 'PENGUJIAN@EXAMPLE.TEST',
    ]))->assertUnprocessable();
});

it('menolak kata sandi yang lemah', function () {
    $this->postJson('/api/v1/registrasi', dataRegistrasi([
        'kata_sandi' => 'password',
        'konfirmasi_kata_sandi' => 'password',
    ]))->assertUnprocessable();
});

it('menolak konfirmasi kata sandi yang tidak cocok', function () {
    $this->postJson('/api/v1/registrasi', dataRegistrasi([
        'konfirmasi_kata_sandi' => 'KataSandiBerbeda123',
    ]))->assertUnprocessable();
});

it('mengembalikan amplop respons yang sesuai kontrak', function () {
    $this->postJson('/api/v1/registrasi', dataRegistrasi())
        ->assertCreated()
        ->assertJsonStructure([
            'sukses',
            'data' => [
                'id',
                'nama',
                'surel',
                'telepon',
                'status',
                'terdaftar_pada',
                'peran',
            ],
            'meta',
        ])
        ->assertJsonPath('sukses', true)
        ->assertJsonPath('data.status', 'aktif')
        ->assertJsonPath('data.peran', ['pembeli']);
});

// =====================================================
// Tiga pengujian tambahan B1
// =====================================================

it('memberi peran pembeli kepada setiap pendaftar, dan hanya itu', function () {
    $this->postJson('/api/v1/registrasi', dataRegistrasi())
        ->assertCreated()
        ->assertJsonPath('data.peran', ['pembeli']);

    $dewi = Pengguna::sole();

    expect($dewi->punyaIzin(NamaIzin::KeranjangKelola))->toBeTrue()
        ->and($dewi->punyaIzin(NamaIzin::ProdukKelola))->toBeFalse();
});

it('mengabaikan peran yang diselipkan klien saat mendaftar', function (mixed $peran) {
    $this->postJson('/api/v1/registrasi', dataRegistrasi([
        'peran' => $peran,
    ]))
        ->assertCreated()
        ->assertJsonPath('data.peran', ['pembeli']);
})->with([
    'teks' => ['admin'],
    'larik' => [['admin', 'keuangan']],
]);

it('tidak meninggalkan akun tanpa peran bila pemberian peran gagal', function () {
    // Meniru kondisi server yang belum memiliki data peran.
    DB::table('peran')->delete();

    $this->postJson('/api/v1/registrasi', dataRegistrasi())
        ->assertStatus(500)
        ->assertJsonPath('galat.kode', 'GALAT_SERVER');

    // Transaksi harus membatalkan pembuatan akun.
    expect(Pengguna::count())->toBe(0);
});

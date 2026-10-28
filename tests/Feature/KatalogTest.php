<?php

declare(strict_types=1);

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\Pengguna;
use App\Enums\StatusAkun;
use App\Enums\StatusProduk;
use App\Enums\StatusToko;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    $this->penggunaAktif = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    $this->penggunaMenunggu = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    $this->tokoAktif = Toko::create([
        'pemilik_id' => $this->penggunaAktif->id,
        'nama' => 'Toko Aktif',
        'slug' => 'toko-aktif',
        'kota' => 'Surakarta',
        'ongkir_rupiah' => 10000,
        'status' => StatusToko::Aktif,
    ]);
    $this->tokoMenunggu = Toko::create([
        'pemilik_id' => $this->penggunaMenunggu->id,
        'nama' => 'Toko Menunggu',
        'slug' => 'toko-menunggu',
        'kota' => 'Surakarta',
        'ongkir_rupiah' => 10000,
        'status' => StatusToko::Menunggu,
    ]);
    $this->kategori = Kategori::factory()->create();
});

it('menampilkan daftar produk yang aktif', function (): void {
    Produk::factory()->count(10)->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
    ]);
    Produk::factory()->count(3)->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
        'status' => StatusProduk::Draf,
    ]);

    $respon = getJson('/api/v1/produk');

    $respon->assertOk()
        ->assertJsonStructure([
            'data' => [
                'meta' => ['total', 'halaman', 'per_halaman'],
                'data' => ['*' => ['id', 'nama', 'slug', 'harga_rupiah', 'status']],
            ],
        ])
        ->assertJson(['data' => ['meta' => ['total' => 10]]]);
});

it('menolak produk draf dan toko yang belum aktif', function (): void {
    Produk::factory()->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
        'nama' => 'Produk Aktif',
    ]);
    Produk::factory()->create([
        'toko_id' => $this->tokoMenunggu->id,
        'kategori_id' => $this->kategori->id,
        'nama' => 'Produk Toko Menunggu',
    ]);
    Produk::factory()->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
        'nama' => 'Produk Draf',
        'status' => StatusProduk::Draf,
    ]);

    $respon = getJson('/api/v1/produk');

    $respon->assertOk()
        ->assertJson(['data' => ['meta' => ['total' => 1]]]);
});

it('mengembalikan 404 untuk produk draf', function (): void {
    $produk = Produk::factory()->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
        'status' => StatusProduk::Draf,
    ]);

    $respon = get("/api/v1/produk/{$produk->slug}");

    $respon->assertNotFound();
});

it('mengembalikan 404 untuk toko yang belum disetujui', function (): void {
    $respon = get("/api/v1/toko/{$this->tokoMenunggu->slug}");

    $respon->assertNotFound();
});

it('memfilter produk berdasarkan pencarian', function (): void {
    Produk::factory()->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
        'nama' => 'Batik Kencana',
    ]);
    Produk::factory()->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
        'nama' => 'Kaos Polos',
    ]);

    $respon = getJson('/api/v1/produk?cari=batik');

    $respon->assertOk()
        ->assertJson(['data' => ['meta' => ['total' => 1]]]);
});

it('mengabaikan karakter % pada pencarian', function (): void {
    Produk::factory()->create([
        'toko_id' => $this->tokoAktif->id,
        'kategori_id' => $this->kategori->id,
    ]);

    $respon = getJson('/api/v1/produk?cari=%25%25');

    $respon->assertOk()
        ->assertJson(['data' => ['meta' => ['total' => 0]]]);
});

it('mengembalikan 422 saat urut tidak ada di daftar putih', function (): void {
    Produk::factory()->create(['toko_id' => $this->tokoAktif->id]);

    $respon = getJson('/api/v1/produk?urut=kata_sandi');

    $respon->assertStatus(422);
});

it('mengurutkan produk sesuai daftar putih', function (): void {
    $murah = Produk::factory()->create(['toko_id' => $this->tokoAktif->id, 'kategori_id' => $this->kategori->id, 'harga_rupiah' => 5000]);
    $mahal = Produk::factory()->create(['toko_id' => $this->tokoAktif->id, 'kategori_id' => $this->kategori->id, 'harga_rupiah' => 50000]);

    $respon = getJson('/api/v1/produk?urut=harga&arah=asc');

    $respon->assertOk()
        ->assertJsonPath('data.data.0.id', $murah->id)
        ->assertJsonPath('data.data.1.id', $mahal->id);
});

it('menampilkan detail toko aktif beserta produknya', function (): void {
    Produk::factory()->count(2)->create(['toko_id' => $this->tokoAktif->id]);

    $respon = get("/api/v1/toko/{$this->tokoAktif->slug}");

    $respon->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'nama', 'slug', 'produk'],
        ]);
});

it('menampilkan daftar kategori', function (): void {
    $kat1 = Kategori::factory()->create(['nama' => 'Makanan']);
    $kat2 = Kategori::factory()->create(['nama' => 'Minuman']);

    $respon = getJson('/api/v1/kategori');

    $respon->assertOk()
        ->assertJsonCount(3, 'data');
});

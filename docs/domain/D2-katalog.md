# D2 — Toko & Katalog

Piagam domain D2 (Toko & Katalog) untuk project Lapak.

## Tabel

| Tabel | Kunci | Keterangan |
|---|---|---|
| `toko` | `id` | Satu pengguna paling banyak satu toko (`pemilik_id` unik). |
| `kategori` | `id` | Daftar kategori produk. |
| `produk` | `id` | Produk belong to toko dan kategori. |

## Relasi

- `Toko::pemilik()` → belongsTo `Pengguna`
- `Toko::produk()` → hasMany `Produk`
- `Produk::toko()` → belongsTo `Toko`
- `Produk::kategori()` → belongsTo `Kategori`
- `Kategori::produk()` → hasMany `Produk`

## Enum

- `StatusToko`: `menunggu`, `aktif`, `ditangguhkan`
- `StatusProduk`: `draf`, `aktif`, `arsip`

## Scope

- `Produk::scopeTampil()` — hanya produk aktif dengan toko aktif.
- `Produk::scopeSaring($cari, $kategori, $hargaMin, $hargaMax)` — filter pencarian.
- `Produk::scopeUrut($urut, $arah)` — pengurutan berbasis daftar putih.

## Endpoint

| Metode | URI | Deskripsi |
|---|---|---|
| GET | `/api/v1/produk` | Daftar produk aktif |
| GET | `/api/v1/produk/{slug}` | Detail satu produk |
| GET | `/api/v1/toko/{slug}` | Detail toko dan produknya |
| GET | `/api/v1/kategori` | Daftar kategori |

## Berkas Milik D2

- `app/Models/Toko.php`
- `app/Models/Kategori.php`
- `app/Models/Produk.php`
- `app/Enums/StatusToko.php`
- `app/Enums/StatusProduk.php`
- `routes/api/katalog.php`
- `app/Http/Controllers/KatalogController.php`
- `app/Http/Requests/KatalogRequest.php`
- `app/Http/Resources/ProdukResource.php`
- `app/Http/Resources/TokoResource.php`
- `app/Http/Resources/KategoriResource.php`
- `database/seeders/KatalogSeeder.php`
- `tests/Feature/KatalogTest.php`

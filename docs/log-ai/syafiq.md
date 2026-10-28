# Log AI — Syafiq

Sesi 1 (Fondasi Tim dan Repositori)

## Bantuan yang Diterima

- AI membantu merancang skema migrasi dan relasi antar tabel (`toko`, `kategori`, `produk`).
- AI membantu menulis `scopeTampil`, `scopeSaring`, dan `scopeUrut` pada model `Produk`.
- AI membantu menyusun `KatalogController` dan `routes/api/katalog.php`.

## Verifikasi Manual

- Menjalankan `php artisan migrate:fresh --seed` dari database kosong.
- Menjalankan `./vendor/bin/pest --filter KatalogTest` untuk memastikan semua kasus uji lulus.
- Memeriksa response JSON melalui `curl` atau Postman untuk setiap endpoint.
- Memastikan produk draf dan toko menunggu tidak bocor (404).

## Alat yang Dipakai

- Hermes Agent (AI lokal)
- Laravel 12 + Pest 3

## Catatan

Tidak ada copy-paste mentah dari AI tanpa penyesuaian; seluruh kode diuji dan disesuaikan dengan konvensi tim.

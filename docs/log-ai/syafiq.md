# Log Penggunaan AI — Syafiq

**Alat yang dipakai**: Google Gemini (AI Chat Assistant)

## Sesi 1 — Fondasi Tim dan Repositori

### A4 — Kontrak Katalog
- Membantu merancang skema migrasi `toko`, `kategori`, `produk` dengan foreign key dan indeks.
- Membantu menulis `scopeTampil` pada model `Produk` (LP-4).
- Membantu menyusun piagam domain D2 (`docs/domain/D2-katalog.md`).
- Membantu menyusun 17 aturan bisnis (`docs/ATURAN-BISNIS.md`).

**Verifikasi**:
- Menjalankan migrasi dari database kosong tanpa error.
- Menjalankan `php artisan pint` dan `php artisan test` (saat itu) untuk memastikan kode kontrak lolos.

### B3 — Implementasi Katalog
- Membantu merancang `scopeSaring` dan `scopeUrut` dengan daftar putih pengurutan.
- Membantu menyusun `KatalogController`, `KatalogRequest`, dan tiga resource (`ProdukResource`, `TokoResource`, `KategoriResource`).
- Membantu menulis `KatalogSeeder` dan `KatalogTest` (10 kasus uji).
- Membantu memperbaiki bug `%` pada pencarian dengan escaping karakter wildcard.

**Verifikasi**:
- Menjalankan `php artisan migrate:fresh --seed` dari database kosong.
- Menjalankan `./vendor/bin/pest --filter KatalogTest` → **10 test lulus** (79 assertions).
- Memeriksa response JSON untuk setiap endpoint via test.

## Catatan

Seluruh kode diuji secara manual sebelum diserahkan. Tidak ada copy-paste mentah tanpa penyesuaian.

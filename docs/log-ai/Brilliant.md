# Log Penggunaan AI — Brilliant

**Alat yang dipakai**: Google Gemini (AI Chat Assistant)

## Sesi 1
**Bagian yang dibantu**: 
  - Berdiskusi mengenai struktur *value object* `Uang.php` dan logika pemecahan belanja di `PenghitungBelanja.php`.
  - Memahami cara penulisan kasus uji (*test cases*) menggunakan Pest.

**Cara memverifikasi hasil**: 
  - Menjalankan perintah `./vendor/bin/pest` secara mandiri di terminal untuk memastikan pengujian bernilai hijau (*passed*).
  - Membaca dan memeriksa ulang kode agar sesuai dengan aturan bisnis Lapak.

## Sesi 2
**Bagian yang dibantu**:
  - Berdiskusi mengenai penegakan aturan bisnis LP-5 (larangan membeli produk toko sendiri) pada pemilah belanja.
  - Memahami perancangan kasus uji keranjang pada `KeranjangTest.php` untuk memastikan sifat idempoten pada endpoint `PUT /api/v1/keranjang/butir/{produkId}`.

**Cara memverifikasi hasil**:
  - Menjalankan `./vendor/bin/pint --test` untuk memastikan kepatuhan standar format kode tim.
  - Menjalankan `php artisan migrate:fresh --seed` dan `./vendor/bin/pest` secara mandiri di terminal untuk memastikan seluruh pengujian hijau.
  - Memeriksa bahwa respons galat LP-5 mengembalikan kode HTTP 422 dan amplop galat yang sesuai kontrak.
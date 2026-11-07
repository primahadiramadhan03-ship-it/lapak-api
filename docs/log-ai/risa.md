# Log Penggunaan AI — Risa

## Sesi 1

- **Alat**: AI Assistant (Qwen dan Google Gemini)
- **Bagian yang dibantu AI**:
  - Memahami alur kerja Git (clone, rebase, push) dan cara menyelesaikan konflik saat push ditolak.
  - Menerjemahkan dan mencerna istilah teknis serta instruksi panjang pada modul PDF.
  - Menjelaskan struktur berkas CODEOWNERS, templat PR, dan aturan penamaan migration.
  - Memahami konsep pembukuan berpasangan (double-entry bookkeeping) untuk domain Dompet (D4).
- **Cara verifikasi**: Setiap kode, perintah terminal, dan isi berkas yang disarankan AI selalu dicocokkan kembali dengan modul PDF resmi, dicocokkan dengan dokumentasi GitHub, serta diuji coba di mesin lokal sebelum di-commit.

## Sesi 2

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Implementasi riwayat mutasi dompet di belakang sesi segar (LP-15).
  - Penyesuaian `MutasiResource` dan pagination pada controller dompet.
  - Pembuatan seeder transaksi contoh dengan rujukan `LPK-CONTOH-0001` (idempoten).
  - Penulisan uji coba alur rotasi kunci sesi dan isolasi mutasi antar penjual.
- **Cara verifikasi**: Menjalankan `./vendor/bin/pest` (seluruh uji hijau), `./vendor/bin/pint --test`, dan `php artisan lapak:periksa-buku` untuk memastikan buku besar tetap seimbang.

## Sesi 3 — B5: Izin Dompet dan Buku Besar untuk Staf

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Menambahkan relasi `Dompet::pemilik()` pada model `Dompet`.
  - Membuat resource `DompetBukuBesarResource` dan `MutasiBukuBesarResource` untuk staf.
  - Membuat controller `BukuBesarController` dengan rute `GET /api/v1/buku-besar/dompet` dan `GET /api/v1/buku-besar/dompet/{id}/mutasi`.
  - Menerapkan perlindungan middleware `auth:sesi`, `izin:dompet.lihat`, dan `sesi.segar` sesuai matriks otorisasi.
  - Memperbarui matriks otorisasi domain D4 di `tests/Feature/Sistem/matriks/dompet.php`.
  - Menyesuaikan pengujian `DompetSayaTest` dan `RiwayatMutasiTest` menggunakan state factory `penjual()`.
  - Menyusun rangkaian uji otomatis di `tests/Feature/Dompet/BukuBesarStafTest.php` untuk memverifikasi isolasi hak akses staf keuangan, auditor, dan penolakan peran lain.
- **Cara verifikasi**:
  - Menjalankan `php artisan migrate:fresh --seed`.
  - Menjalankan seluruh pengujian `./vendor/bin/pest` (208 passed).
  - Memeriksa gaya penulisan kode dengan `./vendor/bin/pint --test` (160 files passed).
  - Menjalankan `php artisan lapak:periksa-buku` untuk memastikan buku besar seimbang.

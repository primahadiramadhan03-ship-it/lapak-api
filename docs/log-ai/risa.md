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


## Sesi 4 — Pembuka: Papan Sesi dan Tangga Kunci

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Memahami arsitektur konkurensi, isolasi transaksi `REPEATABLE-READ`, dan kebutuhan pengujian pada MySQL 8 InnoDB.
  - Menyusun dokumen papan sesi `docs/sesi/SESI-4.md` (pemetaan 13 PR, alur Jendela A & B, mitigasi deadlock).
  - Menyusun tabel Tangga Kunci Baris (Row Lock Hierarchy) dan 3 Aturan Emas pada `docs/PETA-SKEMA.md`.
  - Memperbarui aturan kepemilikan berkas dan integrator pada `.github/CODEOWNERS`.
- **Cara verifikasi**:
  - Memeriksa kesesuaian berkas terhadap modul Hands-On Sesi 4 (Langkah 1).
  - Memastikan konfigurasi database `lapak` dan `lapak_uji` berjalan dengan `REPEATABLE-READ`.
  - Menjalankan `./vendor/bin/pint --test` (183 berkas lulus).
  - Menjalankan seluruh pengujian `./vendor/bin/pest` di SQLite dan MySQL (278 passed).


## Sesi 4 — A5: Kontrak Pembayaran

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Merancang skema tabel `pembayaran` sesuai relasi 1:1 terhadap `checkout` (LP-7) dan foreign key restrictOnDelete.
  - Membuat enum `StatusPembayaran` (`menunggu`, `lunas`, `kedaluwarsa`).
  - Menyusun model `Pembayaran` dengan casting, relasi ke `Checkout`, dan scope `scopeLewatBatas`.
  - Menambahkan relasi `pembayaran()` pada model `Checkout`.
  - Membuat `PembayaranFactory` dan `PembayaranResource`.
  - Mengimplementasikan `LayananPembayaran` versi kontrak (metode `buat`, `untuk`, `tandaiKedaluwarsa` dengan penegakan transaksi database dan `lockForUpdate`).
  - Menambahkan konfigurasi `batas_bayar_jam` pada `config/lapak.php`.
  - Menyusun pengujian unit dan fitur pada `tests/Feature/Dompet/PembayaranTest.php`.
- **Cara verifikasi**:
  - Menjalankan `./vendor/bin/pint --test` (226 files passed).
  - Menjalankan `./vendor/bin/pest` di SQLite (318 passed, 1275 assertions).
  - Menjalankan `DB_CONNECTION=mysql DB_HOST=172.24.192.1 DB_PORT=3306 DB_DATABASE=lapak_uji DB_USERNAME=lapak DB_PASSWORD=rahasia ./vendor/bin/pest` di MySQL 8 InnoDB (318 passed, 1275 assertions).


## Sesi 4 — B4: Webhook Bertanda Tangan dan Dana Tertahan

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Mengimplementasikan `TandaTanganGateway` menggunakan HMAC-SHA256 dengan perlindungan replay serangan waktu dan rahasia bersama.
  - Memutakhirkan `LayananPembayaran` untuk memproses pelunasan (`terima()`) di bawah `GembokCheckout` dan memindahkan dana ke dompet escrow (`tahanDana()`) sesuai tangga kunci baris (pesanan urutan 5, dompet urutan 7).
  - Membuat controller `WebhookPembayaranController` dan `PembayaranSayaController`.
  - Mendaftarkan endpoint publik `POST /api/v1/webhook/pembayaran` dan rute `GET /api/v1/pembayaran/{kode}` pada matriks otorisasi dan rute terlindung.
  - Menambahkan invarian kelima pada perintah `lapak:periksa-buku` untuk memverifikasi kesesuaian saldo escrow dengan dana pesanan tertahan.
  - Membuat perintah `lapak:simulasi-bayar` dan skenario balapan `WebhookGanda` / `BayarGanda`.
  - Menyusun rangkaian pengujian pada `tests/Feature/Dompet/WebhookPembayaranTest.php`.
- **Cara verifikasi**:
  - Menjalankan `./vendor/bin/pint --test` (seluruh berkas lulus).
  - Menjalankan pengujian Pest di SQLite dan MySQL 8 InnoDB.
  - Menjalankan simulasi balapan `php artisan lapak:balapan --skenario=webhook-ganda` di MySQL InnoDB (6 proses serentak, lulus 100% tanpa pelanggaran).


## Sesi 4 — Penutup: Papan Integrasi Final dan Serah Terima ke Sesi 5

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Memverifikasi status akhir seluruh 13 pull request pada papan integrasi `docs/sesi/SESI-4.md`.
  - Mengawal penyelesaian konflik dan temuan balapan kebuntuan (*deadlock*) pada skenario `batal-saat-checkout` hingga terbit dan di-merge PR P1 (`sesi-4/urutan-kunci-rani`).
  - Memutakhirkan catatan penegakan aturan bisnis LP-4, LP-7, LP-9, LP-10, LP-11, LP-12, LP-13, dan LP-15 pada `docs/ATURAN-BISNIS.md`.
  - Menyusun rangkuman penutup sesi, hasil uji konkurensi di atas MySQL 8 InnoDB, dan titipan serah terima untuk Sesi 5 (audit log & observabilitas).
- **Cara verifikasi**:
  - Menjalankan `./vendor/bin/pint --test` (seluruh berkas lulus format).
  - Menjalankan seluruh pengujian `./vendor/bin/pest` di SQLite dan MySQL 8 InnoDB (380 tests passed, 1.607 assertions).
  - Menjalankan verifikasi buku besar `php artisan lapak:periksa-buku` (buku besar seimbang, 0 pelanggaran).
  - Menjalankan seluruh skenario balapan konkurensi MySQL InnoDB (`php artisan lapak:balapan --semua`).


## Sesi 5 — A2: Kontrak Dana, Pencairan, dan Dompet Penahan

- **Alat**: Google Gemini / Antigravity
- **Bagian yang dibantu**:
  - Menambahkan kasus `Dibatalkan = 'dibatalkan'` pada enum `StatusPembayaran` serta memperbarui `akhir()`.
  - Menambahkan `JenisDompet::Penahan` serta pembaruan `DompetSeeder` agar dompet `penahan` dibuat secara idempoten.
  - Membuat migrasi `2026_10_05_400001_buat_tabel_pencairan.php`, enum `StatusPencairan`, model `Pencairan`, dan `PencairanFactory`.
  - Menambahkan metode `BukuBesar::dompetPenjualAtauBuat()` dan `dompetSistemAtauBuat()` berbasis savepoint dan baca ulang `lockForUpdate()` yang aman di bawah `REPEATABLE-READ`.
  - Mengimplementasikan `LayananDana` sebagai pintu tunggal dana keluar dari `escrow` (`lepas()` ke penjual dan `pendapatan` sesuai aturan komisi LP-8, serta `kembalikan()` ke `eksternal` saat pembatalan pesanan berbayar), menjaga urutan kunci dompet sistem lalu dompet penjual.
  - Menambahkan skenario balapan MySQL InnoDB `PelepasanSerentak` (`pelepasan-serentak`) dan rangkaian pengujian `tests/Feature/Dompet/KontrakDanaTest.php`.
- **Cara verifikasi**:
  - Menjalankan `./vendor/bin/pint --test` (268 berkas lulus format).
  - Menjalankan `./vendor/bin/pest` di SQLite dan MySQL 8 InnoDB (393 passed, 1.663 assertions).
  - Menjalankan `php artisan lapak:balapan pelepasan-serentak` di MySQL 8 InnoDB (4/4 proses serentak lulus tanpa deadlock/duplikat) dilanjutkan `php artisan lapak:periksa-buku` (0 pelanggaran).

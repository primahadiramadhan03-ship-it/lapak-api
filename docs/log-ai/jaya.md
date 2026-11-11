# Log Penggunaan AI — Jaya

**Alat yang dipakai**: OpenCode (asisten kode AI)

**Bagian yang dibantu**:
  - Menyusun draf `docs/KONTRAK-API.md`: amplop respons, tabel kode galat, serta aturan uang dan waktu.
  - Menyusun draf `docs/domain/D5-kualitas.md`: piagam domain, daftar berkas yang dimiliki, dan batas kepemilikan.
  - Menelusuri masalah integrasi Docker Desktop dengan WSL saat menyiapkan lingkungan pengembangan.

**Cara memverifikasi hasil**:
  - Membaca ulang dokumen dan mencocokkannya dengan struktur repo serta kode yang sudah ada.
  - Menjalankan `docker compose ps` dan `curl http://localhost:8000/api/v1/tes` untuk memastikan aplikasi berjalan.
  - Memeriksa `git diff` dan `git status` sebelum setiap commit agar tidak ada berkas yang salah ikut.

## Sesi 3 — A1 Kontrak Izin dan Matriks Otorisasi

**Bagian yang dibantu**:
  - Menyusun enum `NamaPeran` dan `NamaIzin` sebagai sumber kebenaran matriks izin.
  - Menyusun model `Peran` dan `Izin`, migration pivot, serta `PeranIzinSeeder` yang idempotent.
  - Menyusun `PemeriksaIzin`, middleware `PastikanIzin` dengan alias `izin:<nama>`, dan command `lapak:matriks-izin`.
  - Menyusun `MatriksOtorisasiTest` untuk memeriksa kesesuaian matriks, seeder, middleware, dan command.
  - Menyesuaikan prioritas middleware, nama tabel pivot, kontrak method, folder matriks domain, dan bagian Otorisasi di `KONTRAK-API.md` berdasarkan review A1.

**Cara memverifikasi hasil**:
  - Menjalankan `php artisan test --filter=MatriksOtorisasiTest` sampai seluruh kasus lulus.
  - Menjalankan `php artisan test` untuk memastikan seluruh pengujian tetap hijau.
  - Menjalankan `php artisan lapak:matriks-izin` dan mencocokkan keluarannya dengan tabel matriks di `docs/sesi/SESI-3.md`.
  - Menjalankan `vendor/bin/pint --test` untuk memastikan gaya kode sesuai.

## Sesi 4 — A1 Kontrak Idempotensi dan Runner Balapan

**Bagian yang dibantu**:
  - Merancang skema migration tabel `kunci_idempotensi` dan model `KunciIdempotensi` dengan relasi `pengguna_id` dan index unik pencegah balapan kunci (LP-11).
  - Menyusun middleware `Idempoten` yang menjamin catatan kunci dikomit satu transaksi bersama efeknya sebelum anak tangga pertama, serta mendukung replay respons tersimpan (`Idempotency-Replayed: true`).
  - Menyusun galat `KonflikIdempotensi` (409) dan `KunciIdempotensiDibutuhkan` (422) sebagai penegakan aturan LP-11.
  - Membangun runner `lapak:balapan` (`Balapan.php`), sub-proses pekerja `lapak:balapan:anak` (`BalapanAnak.php`), kontrak kelas `SkenarioBalapan`, trait `PanggilHttp` (Modul Hal 26), skenario kalibrasi `Serempak` (Modul Hal 26–27), dan skenario `IdempotensiGanda`.
  - Menyusun pengujian kontrak idempotensi pada `tests/Feature/Sistem/IdempotensiTest.php`.
  - Memperbarui dokumentasi `docs/KONTRAK-API.md` (bagian Idempotensi LP-11) dan `docs/domain/D5-kualitas.md`.

**Cara memverifikasi hasil**:
  - Menjalankan migrasi `php artisan migrate:fresh` dan memastikan tabel `kunci_idempotensi` dibuat tanpa tipe pecahan terlarang (LP-1).
  - Menjalankan `php artisan test --filter=IdempotensiTest` pada SQLite.
  - Menjalankan `DB_CONNECTION=mysql DB_DATABASE=lapak_uji ./vendor/bin/pest` untuk memastikan seluruh 288 pengujian lulus 100% pada MySQL 8 InnoDB.
  - Menjalankan `php artisan lapak:balapan serempak` untuk kalibrasi runner MySQL (5 pembalap serentak).
  - Menjalankan `php artisan lapak:balapan --skenario=IdempotensiGanda` dan memverifikasi integritas balapan multi-proses (1 mutasi tercipta, 4 konflik/dicegah, 0 kebocoran data).
  - Menjalankan `./vendor/bin/pint --test` dan memastikan 0 style issues.

## Sesi 4 — B5 Klasifikasi Rute POST, Skenario Lintas Domain, dan CI MySQL

**Bagian yang dibantu**:
  - Menyusun pengujian klasifikasi rute POST `tests/Feature/Sistem/RuteIdempotenTest.php` untuk menegakkan aturan LP-11 (`POST_BERKUNCI` dan `POST_TANPA_KUNCI` dengan alasan tertulis > 20 karakter).
  - Memperbarui perintah `lapak:balapan` (`app/Console/Commands/Balapan.php`) dengan opsi `--semua` untuk menjalankan seluruh skenario balapan berturut-turut.
  - Membangun tiga skenario balapan lintas domain di `app/Balapan/`:
    - `KunciGanda.php`: menguji indeks unik idempotensi pada checkout serentak dengan kunci identik (LP-11).
    - `RebutanCheckout.php`: menguji 12 pembeli berebut stok terbatas lewat seluruh lapisan checkout (LP-4, LP-9, LP-11).
    - `BatalSaatCheckout.php`: mempertemukan pembatalan otomatis dan pembelian produk untuk memverifikasi Tangga Kunci Baris dan mencegah deadlock MySQL 1213.
  - Menambahkan job `kualitas-mysql` pada alur kerja CI `.github/workflows/ci.yml` dengan service container `mysql:8.0` InnoDB untuk pengujian Pest dan uji balapan konkurensi.

**Cara memverifikasi hasil**:
  - Menjalankan `./vendor/bin/pest tests/Feature/Sistem/RuteIdempotenTest.php` (menghasilkan 2 failed dan 2 passed saat draf karena menunggu rute checkout, alamat, stok, dan webhook dari B1–B4).
  - Menjalankan `./vendor/bin/pint --test` dan memastikan seluruh berkas lulus standar format Laravel Pint.
  - Menjalankan `php artisan lapak:balapan --daftar` dan memastikan kelima skenario terdaftar.
  - Menjalankan `./vendor/bin/pest` untuk memastikan pengujian lokal tetap hijau.


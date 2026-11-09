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
  - Menyusun middleware `Idempoten` yang menjamin catatan kunci dikomit satu transaksi bersama efeknya sebelum anak tangga pertama, serta mendukung replay respons tersimpan (`X-Idempotent-Replayed: true`).
  - Menyusun galat `KonflikIdempotensi` (409) dan `KunciIdempotensiDibutuhkan` (400) sebagai penegakan aturan LP-11.
  - Membangun runner `lapak:balapan` (`Balapan.php`), sub-proses pekerja `lapak:balapan:anak` (`BalapanAnak.php`), kontrak kelas `SkenarioBalapan`, dan contoh implementasi skenario `IdempotensiGanda`.
  - Menyusun pengujian kontrak idempotensi pada `tests/Feature/Sistem/IdempotensiTest.php`.
  - Memperbarui dokumentasi `docs/KONTRAK-API.md` (bagian Idempotensi LP-11) dan `docs/domain/D5-kualitas.md`.

**Cara memverifikasi hasil**:
  - Menjalankan migrasi `php artisan migrate:fresh` dan memastikan tabel `kunci_idempotensi` dibuat tanpa tipe pecahan terlarang (LP-1).
  - Menjalankan `php artisan test --filter=IdempotensiTest` pada SQLite.
  - Menjalankan `DB_CONNECTION=mysql DB_DATABASE=lapak_uji ./vendor/bin/pest` untuk memastikan seluruh 286 pengujian lulus 100% pada MySQL 8 InnoDB.
  - Menjalankan `php artisan lapak:balapan --skenario=IdempotensiGanda` dan memverifikasi integritas balapan multi-proses (1 mutasi tercipta, 4 konflik/dicegah, 0 kebocoran data).
  - Menjalankan `./vendor/bin/pint --test` dan memastikan 0 style issues.

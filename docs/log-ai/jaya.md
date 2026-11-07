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

**Cara memverifikasi hasil**:
  - Menjalankan `php artisan test --filter=MatriksOtorisasiTest` sampai seluruh kasus lulus.
  - Menjalankan `php artisan test` untuk memastikan seluruh pengujian tetap hijau.
  - Menjalankan `php artisan lapak:matriks-izin` dan mencocokkan keluarannya dengan tabel matriks di `docs/sesi/SESI-3.md`.
  - Menjalankan `vendor/bin/pint --test` untuk memastikan gaya kode sesuai.

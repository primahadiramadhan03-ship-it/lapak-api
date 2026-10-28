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

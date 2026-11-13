# Log Penggunaan AI — Prima

## Sesi 4

### 1. Pengembangan A2 — Alamat dan Penjaga Transaksi

- **Alat:** ChatGPT.
- **Bagian yang dibantu:**
  - Membantu memahami instruksi dan tahapan pengerjaan.
  - Memberikan panduan penggunaan Git dan GitHub untuk pengelolaan branch, commit, dan pull request.
  - Membantu memahami hasil perintah terminal dan pengujian.
- **Cara verifikasi:**
  - Memeriksa perubahan kode secara mandiri.
  - Menjalankan pengujian Pest.
  - Memastikan seluruh pengujian berhasil sebelum pekerjaan diajukan untuk review.

### 2. Review A3 — Penjaga Stok

- **Alat:** ChatGPT.
- **Bagian yang dibantu:**
  - Membantu menganalisis perubahan kode pada PR #42.
  - Membantu membandingkan implementasi dengan ketentuan kontrak A3.
  - Membantu mengidentifikasi bagian yang termasuk tahap A3 dan bagian yang direncanakan pada B3.
- **Cara verifikasi:**
  - Memeriksa diff PR menggunakan GitHub CLI.
  - Memeriksa hasil CI GitHub.
  - Membandingkan implementasi dengan dokumen Hands-On Sesi 4.
  - Memberikan approval setelah hasil pemeriksaan sesuai.

### 3. Penyusunan Dokumentasi

- **Alat:** ChatGPT.
- **Bagian yang dibantu:** Membantu menyusun struktur dan redaksi log penggunaan AI.
- **Cara verifikasi:** Memeriksa kembali isi dokumentasi agar sesuai dengan aktivitas yang benar-benar dilakukan.

## Sesi 5

### 1. Implementasi B1 — Deteksi Kunci Lama dan Jejak Audit Identitas

- **Alat:** Antigravity AI (Gemini).
- **Bagian yang dibantu:**
  - Panduan implementasi deteksi kunci lama setelah rotasi pada `PenerbitSesi` dan `PemeriksaSesi`.
  - Integrasi pencatatan audit `PencatatAudit` pada `MasukController`, `SesiController`, dan `PengelolaPeran` sesuai LP-19.
  - Pembuatan migrasi penambahan kolom `hash_kunci_lama` pada tabel `sesi`.
  - Penyusunan kasus uji komprehensif pada `tests/Feature/Identitas/JejakIdentitasTest.php`.
- **Cara verifikasi:**
  - Menjalankan `./vendor/bin/pint --test` untuk memastikan kerapian kode.
  - Menjalankan seluruh pengujian `./vendor/bin/pest` untuk memastikan semua uji hijau.
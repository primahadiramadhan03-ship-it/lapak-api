# Sesi 2 — Identitas dan Session dengan Kunci

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #13 | Pembuka | `sesi-2/pembuka-syafiq` | Syafiq (Bagas) | Papan sesi 2 dan pembaruan CODEOWNERS | Risa (Dimas) | - | Merged |
| #14 | A1 | `sesi-2/kontrak-sesi-prima` | Prima (Bambang) | Format kunci, tabel sesi, penerbit, pemeriksa kunci | Brili (Rani) | #13 | Merged |
| #15 | A2 | `sesi-2/kontrak-guard-jaya` | Jaya (Sekar) | Guard sesi, sesi segar, pembatas laju, alat bantu uji `sebagai()` | Syafiq (Bagas) | #14 | Merged |
| #16 | A3 | `sesi-2/kontrak-toko-syafiq` | Syafiq (Bagas) | Penanda kepemilikan (`scopeMilik`, `dimilikiOleh`), GET `/toko-saya` | Risa (Dimas) | #15 | Merged |
| #17 | A4 | `sesi-2/kontrak-keranjang-brili` | Brili (Rani) | Pemilah butir bermasalah, GET `/keranjang` | Jaya (Sekar) | #15 | Merged |
| #18 | A5 | `sesi-2/kontrak-dompet-risa` | Risa (Dimas) | GET `/dompet` (saldo tanpa mutasi) | Prima (Bambang) | #15 | Merged |
| #19 | B1 | `sesi-2/masuk-prima` | Prima (Bambang) | Masuk, keluar, daftar sesi, cabut, konfirmasi sandi | Brili (Rani) | #15 | Merged |
| #20 | B2 | `sesi-2/gerbang-jaya` | Jaya (Sekar) | Uji rute terlindung sapu bersih, pangkas sesi, header baku RFC 6750 & tanpa cache | Syafiq (Bagas) | #19 | Merged |
| #21 | B3 | `sesi-2/toko-saya-syafiq` | Syafiq (Bagas) | Buka toko, ubah toko, kelola produk toko sendiri | Risa (Dimas) | #16 | Merged |
| #22 | B4 | `sesi-2/keranjang-brilliant` | Brili (Rani) | Ubah isi keranjang, aturan LP-5 (larang beli produk sendiri) | Jaya (Sekar) | #16, #17 | Merged |
| #23 | B5 | `sesi-2/riwayat-mutasi-risa` | Risa (Dimas) | Riwayat mutasi di belakang sesi segar, seeder contoh transaksi | Prima (Bambang) | #18, #19 | Merged |
| #24 | Penutup | `sesi-2/penutup-syafiq` | Syafiq (Bagas) | Papan sesi, tempat penegakan aturan bisnis, serah terima Sesi 3 | Brili (Rani) | #23 | Merged |

---

## Urutan Integrasi dan Merge

### 1. Windows A (Kontrak)
Windows A berbentuk rantai ketergantungan:
1. **A1 (Prima)** masuk paling awal karena guard A2 memanggil `PemeriksaSesi` milik A1.
2. **A2 (Jaya)** menyusul setelah A1, menyelesaikan potensi konflik di `config/lapak.php`, menyediakan guard `auth:sesi` dan helper pengujian `sebagai()`.
3. **A3 (Syafiq)**, **A4 (Brili)**, dan **A5 (Risa)** di-merge setelah A2 masuk.

### 2. Windows B (Implementasi)
Urutan merge Windows B:
1. **B1 (Prima)** masuk pertama kali karena rute `POST /masuk` dan `POST /sesi/konfirmasi` dibutuhkan oleh B2 dan B5.
2. **B2 (Jaya)** masuk setelah B1 untuk mengaktifkan gerbang uji tolak bawaan seluruh rute API.
3. **B3 (Syafiq)**, **B4 (Brili)**, dan **B5 (Risa)** menyusul dengan tetap mematuhi pengujian gerbang rute B2.

---

## Catatan Penutup Sesi 2 & Serah Terima ke Sesi 3

Bagas (Syafiq) dan Rani (Brili) mencentang bersama:
1. `main` hijau, seluruh 166 pengujian lolos, gaya kode Pint bersih, buku besar seimbang (`bool(true)`).
2. Variabel pengujian lokal `LAPAK_SESI_SEGAR_MENIT` dipastikan bersih dari `.env`.
3. **Titipan untuk Rani (Domain D3 & Calon Integrator Sesi 3)**:
   - Siapa pun yang masuk saat ini masih boleh membuka toko (otorisasi peran dan persetujuan admin dikerjakan di Sesi 3).
   - Kunci sesi perlu dirotasi saat peran akun berubah; metode `PenerbitSesi::rotasi` sudah tersedia.
   - Daftar `RUTE_PUBLIK` hanya boleh berubah lewat PR yang ditinjau oleh Sekar (Jaya/D5).
4. Kerangka papan sesi untuk Sesi 3 disiapkan pada `docs/sesi/SESI-3.md`.

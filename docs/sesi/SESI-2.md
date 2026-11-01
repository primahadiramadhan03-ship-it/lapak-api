# Sesi 2 — Identitas dan Session dengan Kunci

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #13 | Pembuka | `sesi-2/pembuka-syafiq` | Syafiq (Bagas) | Papan sesi 2 dan pembaruan CODEOWNERS | Risa (Dimas) | - | Open |
| #14 | A1 | `sesi-2/kontrak-sesi-prima` | Prima (Bambang) | Format kunci, tabel sesi, penerbit, pemeriksa kunci | Brili (Rani) | #13 | Menunggu |
| #15 | A2 | `sesi-2/kontrak-guard-jaya` | Jaya (Sekar) | Guard sesi, sesi segar, pembatas laju, alat bantu uji `sebagai()` | Syafiq (Bagas) | #14 | Menunggu |
| #16 | A3 | `sesi-2/kontrak-toko-syafiq` | Syafiq (Bagas) | Penanda kepemilikan (`scopeMilik`, `dimilikiOleh`), GET `/toko-saya` | Risa (Dimas) | #15 | Menunggu |
| #17 | A4 | `sesi-2/kontrak-keranjang-brili` | Brili (Rani) | Pemilah butir bermasalah, GET `/keranjang` | Jaya (Sekar) | #15 | Menunggu |
| #18 | A5 | `sesi-2/kontrak-dompet-risa` | Risa (Dimas) | GET `/dompet` (saldo tanpa mutasi) | Prima (Bambang) | #15 | Menunggu |
| #19 | B1 | `sesi-2/masuk-prima` | Prima (Bambang) | Masuk, keluar, daftar sesi, cabut, konfirmasi sandi | Brili (Rani) | #15 | Menunggu |
| #20 | B2 | `sesi-2/gerbang-jaya` | Jaya (Sekar) | Uji rute terlindung sapu bersih, pangkas sesi, header baku RFC 6750 & tanpa cache | Syafiq (Bagas) | #19 | Menunggu |
| #21 | B3 | `sesi-2/toko-saya-syafiq` | Syafiq (Bagas) | Buka toko, ubah toko, kelola produk toko sendiri | Risa (Dimas) | #16 | Menunggu |
| #22 | B4 | `sesi-2/keranjang-brili` | Brili (Rani) | Ubah isi keranjang, aturan LP-5 (larang beli produk sendiri) | Jaya (Sekar) | #16, #17 | Menunggu |
| #23 | B5 | `sesi-2/mutasi-risa` | Risa (Dimas) | Riwayat mutasi di belakang sesi segar, seeder contoh transaksi | Prima (Bambang) | #18, #19 | Menunggu |
| #24 | Penutup | `sesi-2/penutup-syafiq` | Syafiq (Bagas) | Papan sesi, tempat penegakan aturan bisnis, serah terima Sesi 3 | Brili (Rani) | #23 | Menunggu |

---

## Urutan Integrasi dan Merge

### 1. Windows A (Kontrak)
Windows A berbentuk rantai ketergantungan:
1. **A1 (Prima)** harus masuk paling awal karena guard A2 memanggil `PemeriksaSesi` milik A1.
2. **A2 (Jaya)** menyusul setelah A1, menyelesaikan potensi konflik di `config/lapak.php`, menyediakan guard `auth:sesi` dan helper pengujian `sebagai()`.
3. **A3 (Syafiq)**, **A4 (Brili)**, dan **A5 (Risa)** dapat di-merge setelah A2 masuk.

### 2. Windows B (Implementasi)
Urutan merge Windows B:
1. **B1 (Prima)** masuk pertama kali karena rute `POST /masuk` dan `POST /sesi/konfirmasi` dibutuhkan oleh B2 dan B5.
2. **B2 (Jaya)** masuk setelah B1 untuk mengaktifkan gerbang uji tolak bawaan seluruh rute API.
3. **B3 (Syafiq)**, **B4 (Brili)**, dan **B5 (Risa)** menyusul dengan tetap mematuhi pengujian gerbang rute B2.

---

## Jawaban atas Titipan Sesi 1
Pada catatan penutup Sesi 1 diduga unggahan D5 (Jaya) akan selalu pertama. Pada Sesi 2 dugaan tersebut tidak berlaku karena Windows A berbentuk rantai: Guard milik D5 memanggil `PemeriksaSesi` milik D1 (Prima), dan tiga kontrak lainnya memakai guard tersebut. Oleh karena itu, A1 (Prima) harus masuk lebih dahulu daripada A2 (Jaya).

---

## Catatan Integrator Sesi 2
- Integrator bertanggung jawab memverifikasi status `main` setelah setiap jendela merge.
- Tombol merge hanya ditekan oleh Integrator (Syafiq).
- Verifikasi akhir menargetkan: **155 uji lulus, buku besar seimbang (6 mutasi), dan 25 rute API terlindung**.

# Sesi 3 — Peran, Izin, dan Kewenangan atas Objek

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #25 | Pembuka | `sesi-3/pembuka-rani` | Rani (Brili) | Papan sesi berisi matriks yang disepakati, pembaruan CODEOWNERS | Bambang (Prima) | - | Draft |
| #26 | A1 | `sesi-3/kontrak-izin-sekar` | Sekar (Jaya) | Peran, izin, middleware izin, matriks otorisasi | Rani (Brili) | #25 | Menunggu |
| #27 | A2 | `sesi-3/kontrak-peran-bambang` | Bambang (Prima) | PengelolaPeran, state factory per peran | Dimas (Risa) | #26 | Menunggu |
| #28 | B1 | `sesi-3/peran-pengguna-bambang` | Bambang (Prima) | Peran pendaftar, /saya, pintu admin peran | Dimas (Risa) | #27 | Menunggu |
| #29 | B2 | `sesi-3/ingatan-izin-sekar` | Sekar (Jaya) | Ingatan izin, penjaga pintu peran | Rani (Brili) | #27 | Menunggu |
| #30 | B3 | `sesi-3/moderasi-toko-bagas` | Bagas (Syafiq) | Izin toko dan produk, moderasi oleh admin | Sekar (Jaya) | #27 | Menunggu |
| #31 | B4 | `sesi-3/gerbang-keranjang-rani` | Rani (Brili) | Izin keranjang, aturan status pesanan | Bambang (Prima) | #27 | Menunggu |
| #32 | B5 | `sesi-3/buku-besar-dimas` | Dimas (Risa) | Izin dompet, buku besar untuk staf | Bagas (Syafiq) | #27 | Menunggu |
| #33 | Penutup | `sesi-3/penutup-rani` | Rani (Brili) | Uji izin menganggur, papan sesi, tempat penegakan aturan | Dimas (Risa), Sekar (Jaya) | #32 | Menunggu |

---

## Matriks yang Disepakati di Kelas

| Izin | pembeli | penjual | admin | keuangan | auditor | Keterangan |
|---|:---:|:---:|:---:|:---:|:---:|---|
| `keranjang.kelola` | v | | | | | Mengisi dan mengubah keranjang belanja sendiri |
| `toko.buka` | v | | | | | Mengajukan pembukaan toko |
| `produk.kelola` | | v | | | | Menambah, mengubah, mengarsipkan produk toko sendiri |
| `dompet.lihat` | | v | | | | Melihat saldo dan mutasi dompet sendiri |
| `toko.moderasi` | | | v | | | Menyetujui dan menangguhkan toko |
| `peran.kelola` | | | v | | | Memberi dan mencabut peran pengguna |
| `buku-besar.lihat` | | | | v | v | Membaca seluruh dompet dan mutasi marketplace |

Rute terbuka untuk semua pengguna yang sudah masuk (`*`):
1. `GET api/v1/saya`
2. `GET api/v1/toko-saya`
3. `PATCH api/v1/toko-saya`

---

## Urutan Integrasi dan Merge

### 1. Jendela A (Kontrak)
Hanya ada 2 PR kontrak (berantai):
1. **A1 (#26 - Sekar/Jaya)**: Peran, izin, middleware izin, matriks otorisasi dasar. Masuk pertama kali.
2. **A2 (#27 - Bambang/Prima)**: PengelolaPeran dan state factory peran di PenggunaFactory. Masuk setelah A1.

### 2. Jendela B (Implementasi)
Kelima PR dibuka sebagai draf paralel dari `main` setelah A2 masuk. Urutan merge ke `main`:
1. **B1 (#28 - Bambang/Prima)**
2. **B2 (#29 - Sekar/Jaya)**
3. **B3 (#30 - Bagas/Syafiq)**
4. **B4 (#31 - Rani/Brili)**
5. **B5 (#32 - Dimas/Risa)**
6. **Penutup (#33 - Rani/Brili)**

---

## Catatan Penutup Sesi 3 & Serah Terima ke Sesi 4

*(Akan diisi oleh Rani (Brilliant) pada PR Penutup #33)*
# Sesi 3 — Peran, Izin, dan Kewenangan atas Objek

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #29 | Pembuka | `sesi-3/pembuka-brilliant` | Rani (Brili) | Papan sesi berisi matriks yang disepakati, pembaruan CODEOWNERS | Bambang (Prima) | - | Merged |
| #32 | A1 | `kontrak-izin-dan-matriks-A1` | Sekar (Jaya) | Peran, izin, middleware izin, matriks otorisasi | Rani (Brili) | #29 | Merged |
| #33 | A2 | `sesi-3/kontrak-peran-bambang` | Bambang (Prima) | PengelolaPeran, state factory per peran | Dimas (Risa) | #32 | Merged |
| #38 | B1 | `sesi-3/peran-pengguna-bambang` | Bambang (Prima) | Peran pendaftar, /saya, pintu admin peran | Dimas (Risa) | #33 | Merged |
| #37 | B2 | `ingatan-izin-dan-cache-B2` | Sekar (Jaya) | Ingatan izin, penjaga pintu peran | Rani (Brili) | #33 | Merged |
| #35 | B3 | `sesi-3/moderasi-toko-bagas` | Bagas (Syafiq) | Izin toko dan produk, moderasi oleh admin | Sekar (Jaya) | #33 | Merged |
| #36 | B4 | `sesi-3/gerbang-keranjang-brilliant` | Rani (Brili) | Izin keranjang, aturan status pesanan | Bambang (Prima) | #33 | Merged |
| #34 | B5 | `sesi-3/buku-besar-dimas` | Dimas (Risa) | Izin dompet, buku besar untuk staf | Bagas (Syafiq) | #33 | Merged |
| #39 | Penutup | `sesi-3/penutup-rani` | Rani (Brili) | Papan integrasi final, refleksi sesi, serah terima Sesi 4 | Bambang (Prima), Dimas (Risa), Sekar (Jaya) | #34 | Siap Review |

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
1. **A1 (#32 - Sekar/Jaya)**: Peran, izin, middleware izin, matriks otorisasi dasar. Masuk pertama kali.
2. **A2 (#33 - Bambang/Prima)**: PengelolaPeran dan state factory peran di PenggunaFactory. Masuk setelah A1.

### 2. Jendela B (Implementasi)
Kelima PR dibuka secara paralel dari `main` setelah A2 masuk. Urutan merge ke `main`:
1. **B1 (#38 - Bambang/Prima)**: Peran pendaftar dan pintu admin pengelolaan peran.
2. **B2 (#37 - Sekar/Jaya)**: Cache izin 5 menit dan validasi arsitektur peran.
3. **B3 (#35 - Bagas/Syafiq)**: Moderasi toko dan penyesuaian izin katalog.
4. **B4 (#36 - Rani/Brili)**: Izin keranjang dan daur hidup status pesanan.
5. **B5 (#34 - Dimas/Risa)**: Buku besar staf dan izin dompet.
6. **Penutup (Rani/Brili)**: Papan integrasi final dan verifikasi kesiapan Sesi 4.

---

## Catatan Penutup Sesi 3 & Serah Terima ke Sesi 4

Rani (Brilliant) sebagai Integrator Utama Sesi 3 mencatat hasil verifikasi bersama tim:
1. **Status Uji & Kualitas Kode**:
   - Seluruh **278 pengujian (Pest)** berhasil lulus 100% (*passed*).
   - Format kode (**Laravel Pint**) bersih tanpa pelanggaran (*0 style issues*).
   - Buku besar saldo dipastikan tetap seimbang.
2. **Penegakan Matriks Otorisasi**:
   - Seluruh rute yang dilindungi `auth:sesi` telah terdaftar dan dipetakan pada 4 berkas matriks domain (`identitas.php`, `katalog.php`, `belanja.php`, `dompet.php`).
   - Tidak ada izin yang menganggur (*idle permissions*): seluruh 7 izin bawaan terpetakan ke sedikitnya satu peran.
   - Mekanisme *caching* izin (TTL 5 menit) berjalan konsisten; pencabutan peran melalui `PengelolaPeran::cabut` otomatis mengosongkan cache dan mencabut token sesi.
3. **Titipan Serah Terima untuk Sesi 4 (Daur Hidup Pesanan & Pembayaran)**:
   - Fondasi status pesanan (`MenungguPembayaran`, `Dibayar`, `Diproses`, `Dikirim`, `Selesai`, `Dibatalkan`) beserta matriks transisinya (`AturanTransisi`) sudah siap dipakai di Domain Belanja.
   - Izin `buku-besar.lihat` telah dipagari dan siap digunakan untuk pencatatan transaksi pesanan ke dompet escrow/sistem pada Sesi 4.
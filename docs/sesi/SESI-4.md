# Sesi 4 — Keamanan Transaksi: Checkout, Kunci Stok, Idempotensi

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #34 | Pembuka | `sesi-4/pembuka-dimas` | Dimas (Risa) | Papan sesi, urutan kunci baris pada peta skema, CODEOWNERS | Rani (Brili) | - | Siap Review |
| #35 | A1 | `sesi-4/kontrak-idempoten-sekar` | Sekar (Jaya) | Tabel kunci idempotensi, middleware idempoten, lapak:balapan | Dimas (Risa) | #34 | Menunggu #34 |
| #36 | A2 | `sesi-4/kontrak-alamat-bambang` | Bambang (Prima) | Izin pesanan.buat, tabel alamat, PenjagaTransaksi, batas laju | Sekar (Jaya) | #34 | Menunggu #34 |
| #37 | A3 | `sesi-4/kontrak-stok-bagas` | Bagas (Syafiq) | PenjagaStok versi kontrak | Bambang (Prima) | #34 | Menunggu #34 |
| #38 | A4 | `sesi-4/kontrak-pesanan-rani` | Rani (Brili) | Tabel checkout dan pesanan, penomor, gembok, mesin status | Bagas (Syafiq) | #34 | Menunggu #34 |
| #39 | A5 | `sesi-4/kontrak-pembayaran-dimas` | Dimas (Risa) | Tabel pembayaran, LayananPembayaran versi kontrak | Rani (Brili) | #38 | Menunggu #38 |
| #40 | B1 | `sesi-4/alamat-bambang` | Bambang (Prima) | BukuAlamat dan tiga rute alamat | Sekar (Jaya) | #36 | Menunggu Jendela A |
| #41 | B2 | `sesi-4/checkout-rani` | Rani (Brili) | Checkout atomik, pesanan saya, pembatalan otomatis | Bagas (Syafiq) | #39 | Menunggu Jendela A |
| #42 | B3 | `sesi-4/kunci-stok-bagas` | Bagas (Syafiq) | Kunci baris stok, penyesuaian stok oleh penjual | Bambang (Prima) | #37 | Menunggu Jendela A |
| #43 | B4 | `sesi-4/webhook-dimas` | Dimas (Risa) | Tanda tangan, webhook, dana tertahan, simulasi gerbang | Rani (Brili) | #39 | Menunggu Jendela A |
| #44 | B5 | `sesi-4/balapan-ci-sekar` | Sekar (Jaya) | Klasifikasi rute POST, skenario lintas domain, CI MySQL | Dimas (Risa) | #40–#43, lalu #45 | Menunggu #45 |
| #45 | P1 | `sesi-4/urutan-kunci-rani` | Rani (Brili) | Perbaikan urutan kunci pada pembatalan otomatis (temuan balapan) | Bagas (Syafiq) | Temuan PR #44 | Menunggu Temuan |
| #46 | Penutup | `sesi-4/penutup-dimas` | Dimas (Risa) | Papan sesi final, rekap sesi, serah terima Sesi 5 | Sekar (Jaya) | #44 | Menunggu #44 |

---

## Sasaran Sesi 4
1. **Keamanan Transaksi & Persaingan Data (Race Condition)**: Menjamin integritas data ketika beberapa proses serentak mengakses dan mengubah baris yang sama (stok, keranjang, checkout, dan pembayaran).
2. **Penegakan Tangga Kunci Baris (Row Lock Hierarchy)**: Mencegah terjadinya kebuntuan (*deadlock*) dengan mewajibkan seluruh transaksi mengunci baris sesuai urutan yang disepakati bersama.
3. **Penerapan Idempotensi (LP-11)**: Menjamin pengulangan permintaan mutasi krusial yang membawa `Idempotency-Key` tidak menimbulkan efek ganda (*double charge* atau *duplicate order*).
4. **Verifikasi Webhook Bertanda Tangan**: Memastikan pembaruan status pembayaran hanya dapat dilakukan oleh gerbang pembayaran sah menggunakan tanda tangan HMAC-SHA256.
5. **Uji Balapan pada MySQL 8 InnoDB**: Memverifikasi penguncian baris (`lockForUpdate`) berjalan nyata pada mesin basis data produksi.

---

## Jawaban atas Titipan Sesi 3
1. **Daur Hidup Status Pesanan**: Menggunakan enum `StatusPesanan` dan `AturanTransisi` yang telah dirancang di Sesi 3 untuk mengawal transisi pesanan secara ketat dari `MenungguPembayaran` hingga status akhir (`Selesai` atau `Dibatalkan`).
2. **Izin `buku-besar.lihat` & Pencatatan Transaksi**: Digunakan untuk mengawasi mutasi saldo tertahan (escrow) saat pembayaran berhasil diverifikasi oleh webhook.

---

## 7 Keputusan Rancangan Sesi 4
1. **Arah Foreign Key (LP-7)**: Tabel `pesanan` tidak menunjuk ke `pembayaran` (domain kecil tidak menunjuk domain besar). Keduanya menunjuk ke `checkout`, induk bersama milik D3.
2. **Tangga Kunci Baris**: Transaksi yang mengunci lebih dari satu baris wajib mengambil kunci sesuai urutan menaik 1 sampai 7. Diperbolehkan melompati anak tangga, tetapi dilarang keras berbalik arah.
3. **Tiga Aturan Emas Transaksi Baris**:
   - Penguncian baris hanya boleh diambil di dalam `DB::transaction()`.
   - Ambil kunci dahulu baru baca (`lockForUpdate()`), karena `SELECT` biasa pada MySQL melihat potret snapshot lama (*repeatable read*).
   - Tidak boleh ada panggilan HTTP keluar atau pekerjaan berdurasi panjang di dalam blok transaksi penguncian baris.
4. **Catatan Kunci Idempotensi Satu Transaksi dengan Efeknya**: Catatan `kunci_idempotensi` dan perubahan data berada dalam satu transaksi commit agar tidak meninggalkan kunci tanpa efek atau efek tanpa kunci.
5. **Pembaruan Stok Relatif (`PenjagaStok`)**: Mengubah stok menggunakan perintah relatif (`tambah` / `kurang`) di bawah kunci baris produk urut ID menaik, bukan menimpa angka absolut dari layar.
6. **Hanya Gerbang Pembayaran yang Berkata Lunas**: Endpoint `POST /api/v1/webhook/pembayaran` bersifat publik namun diproteksi menggunakan verifikasi tanda tangan HMAC-SHA256 bersama rahasia gerbang.
7. **Pesanan Kedaluwarsa & Pembatalan Otomatis (LP-10)**: Pesanan yang tidak dibayar dalam 24 jam dibatalkan oleh `lapak:batalkan-kedaluwarsa` dengan memegang `GembokCheckout` untuk mencegah balapan dengan webhook pembayaran.

---

## Urutan Kunci Baris (Tangga Kunci Tim)

| Urut | Baris | Dikunci oleh |
|:---:|---|---|
| **1** | `pengguna`, lalu `alamat` miliknya | `BukuAlamat` |
| **2** | `keranjang`, lalu `butir_keranjang` | `LayananCheckout` |
| **3** | `checkout` | `GembokCheckout` |
| **4** | `pembayaran` | `LayananPembayaran` |
| **5** | `pesanan` | `MesinStatusPesanan` |
| **6** | `produk`, urut nomor ID menaik | `PenjagaStok` |
| **7** | `dompet`, urut nomor ID menaik | `BukuBesar` |

---

## Lingkar Tinjauan Sesi 4
- **Prima (Bambang)** ditinjau oleh **Jaya (Sekar)**
- **Jaya (Sekar)** ditinjau oleh **Risa (Dimas)**
- **Risa (Dimas)** ditinjau oleh **Brili (Rani)**
- **Brili (Rani)** ditinjau oleh **Syafiq (Bagas)**
- **Syafiq (Bagas)** ditinjau oleh **Prima (Bambang)**

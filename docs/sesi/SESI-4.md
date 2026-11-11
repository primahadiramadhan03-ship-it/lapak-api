# Sesi 4 — Keamanan Transaksi: Checkout, Kunci Stok, Idempotensi

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #34 | Pembuka | `sesi-4/pembuka-dimas` | Dimas (Risa) | Papan sesi, urutan kunci baris pada peta skema, CODEOWNERS | Rani (Brili) | - | Merged |
| #35 | A1 | `sesi-4/kontrak-idempoten-sekar` | Sekar (Jaya) | Tabel kunci idempotensi, middleware idempoten, lapak:balapan | Dimas (Risa) | #34 | Merged |
| #36 | A2 | `sesi-4/kontrak-alamat-bambang` | Bambang (Prima) | Izin pesanan.buat, tabel alamat, PenjagaTransaksi, batas laju | Sekar (Jaya) | #34 | Merged |
| #37 | A3 | `sesi-4/kontrak-stok-bagas` | Bagas (Syafiq) | PenjagaStok versi kontrak | Bambang (Prima) | #34 | Merged |
| #38 | A4 | `sesi-4/kontrak-pesanan-rani` | Rani (Brili) | Tabel checkout dan pesanan, penomor, gembok, mesin status | Bagas (Syafiq) | #34 | Merged |
| #46 | A5 | `sesi-4/kontrak-pembayaran-dimas` | Dimas (Risa) | Tabel pembayaran, LayananPembayaran versi kontrak | Rani (Brili) | #38 | Merged |
| #51 | B1 | `sesi-4/alamat-bambang` | Bambang (Prima) | BukuAlamat dan tiga rute alamat | Sekar (Jaya) | #36 | Merged |
| #48 | B2 | `sesi-4/checkout-rani` | Rani (Brili) | Checkout atomik, pesanan saya, pembatalan otomatis | Bagas (Syafiq) | #39 | Merged |
| #47 | B3 | `sesi-4/kunci-stok-bagas` | Bagas (Syafiq) | Kunci baris stok, penyesuaian stok oleh penjual | Bambang (Prima) | #37 | Merged |
| #49 | B4 | `sesi-4/webhook-dimas` | Dimas (Risa) | Tanda tangan, webhook, dana tertahan, simulasi gerbang | Rani (Brili) | #39 | Merged |
| #52 | P1 | `sesi-4/urutan-kunci-rani` | Rani (Brili) | Perbaikan urutan kunci pada pembatalan otomatis (temuan balapan) | Bagas (Syafiq) | Temuan PR #50 | Merged |
| #50 | B5 | `sesi-4/balapan-ci-sekar` | Sekar (Jaya) | Klasifikasi rute POST, skenario lintas domain, CI MySQL | Dimas (Risa) | #52 | Merged |
| #53 | Penutup | `sesi-4/penutup-dimas` | Dimas (Risa) | Papan sesi final, rekap sesi, serah terima Sesi 5 | Sekar (Jaya) | #50 | Siap Review |

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

---

## Catatan Penutup Sesi 4 & Serah Terima ke Sesi 5

Dimas (Risa) sebagai Integrator Utama Sesi 4 mencatat hasil verifikasi bersama tim:

1. **Status Uji & Kualitas Kode**:
   - Seluruh **380 pengujian (Pest)** berhasil lulus 100% (*passed*, 1.607 assertions).
   - Format kode (**Laravel Pint**) bersih tanpa pelanggaran (**256 berkas rapi**).
   - Buku besar saldo dipastikan tetap seimbang dan terverifikasi via `php artisan lapak:periksa-buku` (0 pelanggaran).
   - Alur kerja CI GitHub Actions (`Gerbang Kualitas` dan `kualitas-mysql` dengan MySQL 8 InnoDB) lulus hijau.

2. **Penegakan Tangga Kunci Baris (Row Lock Hierarchy)**:
   - Tangga kunci baris tingkat 1 sampai 7 ditegakkan secara konsisten di seluruh lapisan layanan:
     `pengguna/alamat` (1) ➔ `keranjang/butir` (2) ➔ `checkout` (3) ➔ `pembayaran` (4) ➔ `pesanan` (5) ➔ `produk` (6) ➔ `dompet` (7).
   - Tiga aturan emas transaksi baris ditaati: penguncian hanya di dalam transaksi, kunci sebelum baca (`lockForUpdate`), dan tidak ada pemanggilan eksternal di dalam blok penguncian.

3. **Hasil Simulasi & Uji Balapan Konkurensi (MySQL 8 InnoDB)**:
   - Seluruh skenario balapan konkurensi diuji pada MySQL 8 InnoDB dan terbukti aman tanpa kebocoran (*zero race condition*):
     - `alamat-pertama`: Menjaga batas maksimal 5 alamat dan keunikan alamat utama saat penambahan serentak.
     - `kunci-ganda` (LP-11): 6 proses serentak dengan kunci idempotensi identik menghasilkan tepat 1 checkout atomik.
     - `rebutan-stok` & `rebutan-checkout` (LP-4, LP-9, LP-11): 12 pembalap serentak berebut stok terbatas tidak menghasilkan *overselling* atau mutasi separuh jalan.
     - `penyesuaian-stok`: Penjual mengubah stok relatif tanpa ada pembaruan yang hilang.
     - `keranjang-ganda`: Checkout bersamaan atas satu keranjang hanya melahirkan 1 checkout valid.
     - `webhook-ganda` (LP-12, LP-13): Webhook kembar melunasi tagihan dan menahan dana di escrow tepat 1 kali.
     - `batal-saat-checkout`: Pembatalan otomatis bertemu checkout produk yang sama bebas dari kebuntuan (*deadlock*).

4. **Temuan Balapan & Perbaikan Kunci (PR P1)**:
   - Skenario balapan lintas domain `batal-saat-checkout` pada PR B5 mendeteksi potensi kebuntuan (*deadlock*) saat pengembalian stok pada pembatalan otomatis.
   - Temuan ini berhasil diselesaikan oleh Rani (Brili) melalui **PR P1 (`sesi-4/urutan-kunci-rani`)** dengan mengurutkan pengembalian stok berdasarkan `produk_id` menaik sesuai Tangga Kunci Baris sebelum PR B5 di-merge.

5. **Titipan Serah Terima untuk Sesi 5 (Audit Log & Observabilitas)**:
   - Arsitektur transaksi checkout atomik, pembayaran, kunci stok, dan penahanan dana di dompet escrow telah teruji kokoh di MySQL InnoDB.
   - Idempotensi mutasi krusial (`Idempotency-Key`) dan verifikasi webhook bertanda tangan (`HMAC-SHA256`) sudah aktif penuh, siap diintegrasikan dengan rantai log audit berantai hash (`log_audit`) pada Sesi 5.

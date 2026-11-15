# Catatan Perubahan (Changelog) — Lapak Marketplace

Seluruh perubahan penting pada proyek Lapak Marketplace didokumentasikan di berkas ini. Format penulisan mengacu pada [Keep a Changelog](https://keepachangelog.com/id/1.0.0/) dan mengikuti kaidah [Semantic Versioning](https://semver.org/lang/id/).

---

## [1.0.0] - 2026-11-15

Rilis resmi pertama yang menandai kesiapan produksi dengan alur transaksi lengkap (*end-to-end*): mulai dari pendaftaran pengguna, katalog produk, belanja & checkout, pembayaran, penahanan dana di escrow, pemrosesan pesanan oleh penjual, pelepasan dana, pencairan saldo ke rekening, hingga rantai log audit tak-terubahkan (*immutable*) ber-hash SHA-256.

### Perubahan yang Memutus Kompatibilitas (Breaking Changes)

1. **Deteksi Pemakaian Kunci Sesi Lama (LP-14, LP-15)**:
   - Pemanggilan endpoint menggunakan kunci sesi lama yang sudah pernah dirotasi sekarang langsung **mencabut sesi pengguna secara permanen**. Kunci baru pada sesi tersebut tidak dapat lagi digunakan dan server merespons dengan `401 Unauthorized`.
2. **Pintu Tunggal Pasca-Checkout (LP-10, LP-18)**:
   - Pembatalan pesanan atau checkout tidak lagi dapat memanipulasi stok atau saldo secara langsung. Seluruh perubahan wajib melalui `LayananPesanan`, yang secara atomik mengembalikan stok berurutan nomor ID produk dan mengembalikan dana dari status escrow ke saldo pembeli.
3. **Pemisahan Otorisasi & Peran Baru (LP-2, LP-16)**:
   - Penambahan peran `keuangan` dan `auditor` dengan matriks izin yang lebih ketat. Staf auditor hanya memiliki izin baca (`buku-besar.lihat`, `audit.lihat`) dan tidak dapat memutuskan pencairan. Seluruh endpoint pencairan dan audit log menuntut `sesi.segar` (konfirmasi ulang kata sandi dalam 10 menit terakhir).
4. **Penambahan Skema Basis Data**:
   - Kolom baru `hash_kunci_lama` pada tabel `sesi`.
   - Tabel baru: `pencairan` (D4), `log_audit` (D5), dan `rantai_audit` (D5).

---

### Fitur Baru (Added)

#### D1 — Identitas & Sesi
- **Deteksi Kunci Lama**: Penyimpanan `hash_kunci_lama` saat rotasi kunci sesi.
- **Jejak Audit Identitas**: Pencatatan audit otomatis untuk peristiwa `masuk.berhasil`, `masuk.gagal` (tanpa membocorkan kata sandi), `sesi.dicabut`, serta `peran.diberikan` dan `peran.dicabut`.

#### D2 — Toko & Katalog
- **Pintu Penjual atas Pesanan**: Endpoint pemrosesan pesanan toko untuk tindakan `kemas` dan `kirim` (wajib menyertakan nomor resi) dengan perlindungan izin `pesanan.proses`.
- **Jejak Audit Moderasi Toko**: Pencatatan otomatis peristiwa `toko.disetujui` dan `toko.ditangguhkan`.

#### D3 — Keranjang & Pesanan
- **Daur Hidup Pesanan Selesai / Batal**:
  - Pintu pembeli untuk mengonfirmasi penerimaan pesanan (`pesanan.diterima`) yang memicu pelepasan dana dari escrow ke saldo penjual.
  - Pembatalan pesanan sebelum barang dikirim (`pesanan.dibatalkan`) dengan pemulihan stok urut ID dan pengembalian dana ke pembeli.
  - Pembatalan tagihan checkout yang belum dibayar (`checkout.dibatalkan`).
- **Penyelesaian Otomatis**: Perintah penjadwal untuk menyelesaikan pesanan yang melewati masa tahan (`lama_tahan_hari`) tanpa tombol terima dari pembeli (`pesanan.selesai_otomatis`).

#### D4 — Pembayaran & Saldo
- **Escrow & Pelepasan Dana (LP-18)**: Pintu tunggal `LayananDana` untuk pelepasan dana ke dompet penjual (`dana.dilepas`) dan pengembalian dana ke pembeli (`dana.dikembalikan`).
- **Pencairan Saldo Berpenahan**: Pintu tunggal `LayananPencairan` bagi penjual untuk mengajukan pencairan saldo (minimal Rp 50.000) yang langsung dipindahkan ke dompet sistem `penahan`.
- **Putusan Orang Kedua (*Four-Eyes Principle*)**: Pencairan disetujui atau ditolak oleh staf keuangan dengan validasi ketat bahwa pemutus tidak boleh sama dengan pengaju (`403 Forbidden`).
- **Penanganan Pembayaran Terlambat (LP-13)**: Pembayaran yang tiba setelah tagihan kedaluwarsa dibukukan masuk lalu keluar kembali secara seimbang (`pembayaran.dikembalikan`).
- **Invarian Keenam Buku Besar**: Validasi pada perintah `lapak:periksa-buku` bahwa saldo dompet `penahan` harus sama persis dengan total nominal pengajuan pencairan berstatus `Menunggu`.

#### D5 — Otorisasi, Audit & Kualitas
- **Rantai Log Audit SHA-256 (LP-19)**:
  - Pencatatan seluruh peristiwa penting aplikasi ke tabel `log_audit` dengan tautan rantai hash SHA-256 (`hash_sebelumnya`).
  - Uji arsitektur ketat: memastikan hanya `PencatatAudit` yang diizinkan menulis ke tabel `log_audit`.
- **Verifikasi Integritas Rantai Audit**: Perintah `php artisan lapak:verifikasi-audit` untuk memeriksa keutuhan rantai hash dan mendeteksi perusakan data forensik secara instan.
- **Pintu Baca Auditor**: Endpoint `GET /api/v1/audit/log` dan `GET /api/v1/audit/log/{id}` dengan otorisasi peran `auditor` dan `sesi.segar`.
- **Pemangkasan Idempotensi**: Perintah `php artisan lapak:pangkas-idempotensi` untuk menghapus kunci idempotensi kadaluwarsa (bawaan 7 hari).
- **Jadwal Otomatis & CI**: Penjadwalan harian di `routes/console.php` dan langkah `lapak:verifikasi-audit` pada alur kerja GitHub Actions CI.

---

### Langkah Naik Versi Bagi Pengelola Server (Upgrade Guide)

Untuk memperbarui sistem dari versi rilis sebelumnya (`sesi-4`) ke versi `v1.0.0`, jalankan perintah berikut secara berurutan:

```bash
# 1. Jalankan migrasi basis data untuk skema baru (sesi, pencairan, log_audit, rantai_audit)
php artisan migrate --force

# 2. WAJIB: Jalankan seeder pembaruan peran dan izin
php artisan db:seed --class=PeranIzinSeeder --force
```

> [!IMPORTANT]
> **Peringatan Penting Pengelola Server:**
> Perintah `php artisan migrate` **tidak** menjalankan `PeranIzinSeeder` secara otomatis. Pengelola server **wajib** menjalankan `php artisan db:seed --class=PeranIzinSeeder --force` secara eksplisit.
> 
> Tanpa langkah ini, izin baru (`pencairan.ajukan`, `pencairan.setujui`, `audit.lihat`, `pesanan.proses`) serta peran `keuangan` dan `auditor` tidak akan terdaftar di basis data, sehingga fitur pencairan saldo, proses pesanan penjual, dan audit log tidak dapat diakses.

---

### Riwayat Integrasi Git Sesi 5 (Kronologis)

1. PR #54 (`sesi-5/pembuka-sekar`): Pembuka Sesi 5, papan integrasi, dan penataan rute bersama.
2. PR #55 (`sesi-5/kontrak-audit-sekar`): Kontrak audit: tabel log audit, rantai hash, dan daftar peristiwa (A1).
3. PR #56 (`sesi-5/kontrak-dana-dimas`): Kontrak dana: tabel pencairan, dompet penahan, dan LayananDana (A2).
4. PR #57 (`sesi-5/kontrak-pesanan-rani`): Kontrak pesanan: LayananPesanan sebagai pintu tunggal transaksi (A3).
5. PR #58 (`sesi-5/jejak-identitas-bambang`): Deteksi kunci lama, pencabutan sesi, dan jejak audit identitas & peran (B1).
6. PR #59 (`sesi-5/pesanan-toko-bagas`): Pintu pesanan bagi penjual (`pesanan.proses`) dan jejak moderasi toko (B2).
7. PR #60 (`sesi-5/tindakan-pembeli-rani`): Tindakan pembeli (terima/batal) dan penyelesaian otomatis masa tahan (B3).
8. PR #61 (`sesi-5/pencairan-dimas`): Pencairan saldo penjual, putusan orang kedua, dan Invarian Keenam buku besar (B4).
9. PR #62 (`sesi-5/rantai-audit-sekar`): Pemeriksa rantai audit, perintah verifikasi, pintu auditor, dan pangkas idempotensi (B5).


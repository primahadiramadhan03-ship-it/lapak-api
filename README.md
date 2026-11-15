# Lapak — Marketplace UMKM Solo Raya

Lapak adalah platform marketplace backend berbasis Laravel yang dirancang untuk mendukung ekosistem UMKM di wilayah Solo Raya. Proyek ini dibangun dengan penegakan integritas data ketat, arsitektur berbasis peran (RBAC), rekonsiliasi buku besar mutasi saldo, keamanan idempotensi mutasi krusial, dompet escrow, dan rantai log audit berintegritas tinggi (*cryptographic hash chain*).

## Versi Rilis
- **Versi**: `1.0.0`
- **Sesi Pengembangan**: Sesi 1 sampai Sesi 5 (Final Release)

---

## Fitur Utama

1. **Otorisasi Berbasis Peran (RBAC) & Kunci Sesi**:
   - Multi-peran: `pembeli`, `penjual`, `admin`, `keuangan`, dan `auditor`.
   - Sesi terotentikasi menggunakan pengenal dan rahasia terenkripsi/hash SHA256 (`auth:sesi`).
   - Sesi segar (`sesi.segar`) untuk transaksi bernilai tinggi atau perubahan data sensitif.
2. **Katalog & Penguncian Stok Atomik**:
   - Produk tampil jika produk dan tokonya berstatus aktif.
   - Penyesuaian stok aman dari *race condition* dengan penguncian baris (`lockForUpdate`).
3. **Keranjang & Daur Hidup Pesanan**:
   - Perhitungan belanja dan ongkir dihitung ulang secara ketat di server (LP-6, LP-8).
   - Daur hidup pesanan dipusatkan pada satu pintu (`LayananPesanan`): Menunggu Pembayaran -> Dibayar -> Dikemas -> Dikirim -> Selesai / Dibatalkan.
   - Pembatalan otomatis 24 jam dengan pemulihan stok urut ID produk (`lapak:batalkan-kedaluwarsa`).
4. **Dompet, Escrow, dan Pembayaran**:
   - Penahanan dana pembeli di dompet sistem `escrow` saat tagihan terbayar.
   - Pelepasan dana ke dompet penjual setelah pesanan selesai, atau pengembalian penuh ke pembeli jika pesanan dibatalkan (`LayananDana`).
   - Pencairan saldo penjual dengan sistem dompet penahan (`penahan`) dan validasi *dual-control* / persetujuan staf keuangan kedua (`LayananPencairan`).
5. **Idempotensi & Keamanan Transaksi**:
   - Proteksi eksekusi ganda melalui header `Idempotency-Key` untuk mutasi krusial (LP-11).
   - Verifikasi tanda tangan webhook pembayaran gerbang menggunakan HMAC-SHA256 (`X-Gateway-Signature`).
6. **Rantai Log Audit Berintegritas (Hash Chain)**:
   - Pencatatan seluruh mutasi uang, stok, kewenangan, dan sesi ke tabel `log_audit` hanya melalui `PencatatAudit` (LP-19).
   - Setiap baris mengunci hash baris sebelumnya; integritas rantai diawasi dan dapat diverifikasi kapan saja via `lapak:verifikasi-audit`.

---

## Persyaratan Sistem

- PHP >= 8.2 (ekstensi: `pdo`, `pdo_mysql`, `bcmath`, `mbstring`, `openssl`)
- Composer >= 2.0
- MySQL 8.0+ / MariaDB 10.5+ (InnoDB Engine)

---

## Panduan Pemasangan Bersih

1. **Klon Repositori**:
   ```bash
   git clone https://github.com/primahadiramadhan03-ship-it/lapak-api.git
   cd lapak-api
   ```

2. **Pasang Dependensi**:
   ```bash
   composer install
   ```

3. **Konfigurasi Lingkungan**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Sesuaikan kredensial basis data pada berkas `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=lapak
   DB_USERNAME=root
   DB_PASSWORD=rahasia
   LAPAK_GATEWAY_RAHASIA=kunci-rahasia-gateway-anda
   ```

4. **Migrasi dan Pengisian Data Awal (Seeding)**:
   ```bash
   php artisan migrate:fresh --seed
   ```

---

## Panduan Naik Versi (Upgrade dari Sesi 4)

Bagi instalasi lama yang memperbarui sistem ke `v1.0.0`:
```bash
php artisan migrate
php artisan db:seed --class=PeranIzinSeeder --force
```
> **PENTING**: Perintah `php artisan db:seed --class=PeranIzinSeeder --force` **wajib** dijalankan agar izin-izin baru (`pesanan.proses`, `pencairan.ajukan`, `pencairan.setujui`, `audit.lihat`) terpasang pada tabel peran dan pivot basis data.

---

## Menjalankan Pengujian dan Verifikasi

### 1. Pengujian Otomatis (Unit & Feature Test)
```bash
./vendor/bin/pest
```

### 2. Standar Format Kode (Laravel Pint)
```bash
./vendor/bin/pint --test
```

### 3. Simulasi Balapan Konkurensi (MySQL InnoDB)
```bash
php artisan lapak:balapan --semua
```

### 4. Verifikasi Integritas Buku Besar & Log Audit
```bash
# Memeriksa keseimbangan mutasi saldo dompet
php artisan lapak:periksa-buku

# Memverifikasi rantai hash log audit dari baris pertama hingga kepala rantai
php artisan lapak:verifikasi-audit --rinci
```

---

## Tim Pengembang (Sesi 5)

- **Prima Hadi Ramadhan (Bambang)** — D1: Identitas & Sesi
- **Syafiq Syadi (Bagas)** — D2: Toko & Katalog
- **Briliant (Rani)** — D3: Keranjang & Pesanan
- **Risa (Dimas)** — D4: Pembayaran & Saldo
- **Jaya (Sekar)** — D5: Otorisasi, Audit & Kualitas (Integrator Utama Sesi 5)

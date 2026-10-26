# Peta Skema Basis Data — Lapak

## Kepemilikan Tabel per Domain
- **D1 (Identitas & Sesi)**: `pengguna`, `sesi`[cite: 6]
- **D2 (Toko & Katalog)**: `toko`, `kategori`, `produk`[cite: 6]
- **D3 (Keranjang & Pesanan)**: `keranjang`, `butir_keranjang`, `pesanan`, `butir_pesanan`[cite: 6]
- **D4 (Pembayaran & Saldo)**: `dompet`, `mutasi_saldo`, `pembayaran`, `pencairan`[cite: 6]
- **D5 (Otorisasi, Audit & Kualitas)**: `peran`, `izin`, `kunci_idempotensi`, `log_audit`[cite: 6]

## Pola Penomoran Migration
Format nama berkas migration: `2026_10_SS_DNNNNN_nama.php` (SS = nomor sesi, D = nomor domain, NNNNN = nomor urut).
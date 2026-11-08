# Peta Skema Basis Data — Lapak

## Kepemilikan Tabel per Domain

| Domain | Penanggung Jawab | Tabel | Lahir pada |
| --- | --- | --- | --- |
| D1 Identitas dan Sesi | Bambang (Prima) | `pengguna` | Sesi 1 |
| D1 | Bambang (Prima) | `sesi` | Sesi 2 |
| D1 | Bambang (Prima) | `alamat` | Sesi 4 |
| D2 Toko dan Katalog | Bagas (Syafiq) | `toko`, `kategori`, `produk` | Sesi 1 |
| D3 Keranjang dan Pesanan | Rani (Brili) | `keranjang`, `butir_keranjang` | Sesi 1 |
| D3 | Rani (Brili) | `checkout`, `pesanan`, `butir_pesanan` | Sesi 4 |
| D4 Pembayaran dan Saldo | Dimas (Risa) | `dompet`, `mutasi_saldo` | Sesi 1 |
| D4 | Dimas (Risa) | `pembayaran` | Sesi 4 |
| D5 Otorisasi, Audit & Kualitas | Sekar (Jaya) | `peran`, `izin`, `pengguna_peran`, `peran_izin` | Sesi 3 |
| D5 | Sekar (Jaya) | `kunci_idempotensi` | Sesi 4 |

## Arah Foreign Key

```text
pengguna (D1) <- dompet (D4) <- mutasi_saldo (D4)
pengguna (D1) <- alamat (D1)
pengguna (D1) <- checkout (D3) <- pesanan (D3) <- butir_pesanan (D3)
toko (D2) <- pesanan (D3)          produk (D2) <- butir_pesanan (D3)
checkout (D3) <- pembayaran (D4)
pengguna (D1) <- kunci_idempotensi (D5)
```

`pesanan` tidak menunjuk ke `pembayaran`, walau itu yang pertama terpikir: D3 tidak boleh menunjuk ke D4. Keduanya menunjuk ke `checkout`, induk bersama milik D3 (LP-7). `pesanan` juga tidak menunjuk ke `alamat`: alamat disalin ke kolom `alamat_kirim` pada saat checkout.

Bila sebuah tabel domain bernomor kecil terasa perlu menunjuk ke domain bernomor besar, buat induk bersama atau salin nilainya.

## Pola Penomoran Migration
Format nama berkas migration: `2026_10_SS_DNNNNN_nama.php` (SS = nomor sesi, D = nomor domain, NNNNN = nomor urut).
- Tidak ada `float`, `double`, atau `decimal`. Uji arsitektur menolak migration yang memakainya. Gunakan bilangan bulat terkecil (rupiah utuh).

## Urutan kunci baris

Berlaku sejak Sesi 4. Transaksi yang mengunci lebih dari satu baris mengambil kuncinya menurut urutan di bawah ini. Sebuah transaksi boleh melompati anak tangga, tetapi tidak pernah naik kembali.

| Urut | Baris | Dikunci oleh |
| --- | --- | --- |
| 1 | `pengguna`, lalu `alamat` miliknya | `BukuAlamat` |
| 2 | `keranjang`, lalu `butir_keranjang` | `LayananCheckout` |
| 3 | `checkout` | `GembokCheckout` |
| 4 | `pembayaran` | `LayananPembayaran` |
| 5 | `pesanan` | `MesinStatusPesanan` |
| 6 | `produk`, urut nomor menaik | `PenjagaStok` |
| 7 | `dompet`, urut nomor menaik | `BukuBesar` |

Middleware `idempoten` mencatat kuncinya sebelum anak tangga pertama. Catatan itu baris baru, dan tidak ada transaksi yang memintanya setelah memegang kunci dari tabel di atas.

Tiga aturan menyertainya:

1. Kunci hanya diambil di dalam `DB::transaction()`. Di luar transaksi, kunci lepas begitu pernyataannya selesai.
2. Kunci dulu, baca kemudian. Data yang menjadi dasar keputusan dibaca dengan `lockForUpdate()` setelah kunci induknya didapat. Pada MySQL, `SELECT` biasa di dalam transaksi melihat potret lama, bukan keadaan terkini.
3. Tidak ada panggilan ke layanan luar dan tidak ada pekerjaan lama di dalam transaksi. Selama transaksi terbuka, pembeli lain menunggu di depan baris yang dikuncinya.

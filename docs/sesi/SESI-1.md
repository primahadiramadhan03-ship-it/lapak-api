# Sesi 1 — Fondasi Tim dan Repositori

## Papan Sesi

| PR | Judul | Penulis | Reviewer | Status |
|---|---|---|---|---|
| #1 | Fondasi repositori dan inisialisasi | Prima | Syafiq | Merged |
| #2 | Trait ResponsApi dan induk galat | Jaya | Prima | Merged |
| #3 | Value object Uang | Briliant | Risa | Merged |
| #4 | Tabel pengguna, enum, model | Prima | Syafiq | Merged |
| #5 | Skema toko, kategori, produk (A4) | Syafiq | Briliant | Merged |
| #6 | Dompet dan mutasi saldo (A5) | Risa | Jaya | Merged |
| #7 | Penangan galat, middleware, CI | Jaya | Prima | Merged |
| #8 | Endpoint registrasi | Prima | Syafiq | **Draft** |
| #9 | Endpoint katalog (B3) | Syafiq | Briliant | **Draft** |
| #10 | Keranjang & simulasi belanja (B4) | Briliant | Risa | Open |
| #11 | Buku besar, saldo, seeder (B5) | Risa | Jaya | Open |
| #12 | PR Penutup sesi | Prima | Syafiq | Belum dibuat |

## Urutan Merge Jendela A

A1 → A2 → A3 → A4 → A5

## Urutan Merge Jendela B

B1 → B2 → B3 → B4 → B5

## Titik Kritis

- Setelah B1 masuk, CI berjalan pertama kali. Branch protection di-aktifkan.
- Konflik `DatabaseSeeder.php` antara Syafiq (KatalogSeeder) dan Risa (DompetSeeder) diselesaikan oleh Risa dengan menyatukan kedua baris.
- Risa menambahkan `lapak:periksa-buku` ke CI workflow B1.

## Target Verifikasi

- Pest: 55 tes lulus (192 assertion)
- Buku besar seimbang: 0 mutasi, 0 pelanggaran
- 6 rute API aktif tanpa konflik

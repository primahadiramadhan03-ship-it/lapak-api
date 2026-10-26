# D2 — Toko & Katalog

## Pemilik
Syafiq (akun GitHub: @Syafiq-web)

## Tanggung Jawab
Domain ini menjawab pertanyaan: **Apa yang dijual, oleh siapa, dan mana yang boleh tampil?**

## Tabel yang Dikelola
| Tabel | Fungsi |
|---|---|
| `toko` | Menyimpan profil toko, relasi pemilik (`pemilik_id`), ongkir, dan status persetujuan toko |
| `kategori` | Klasifikasi produk UMKM |
| `produk` | Katalog produk, harga rupiah, stok, berat, dan status draf/aktif/arsip |

## Endpoint yang Dikelola
- `GET /api/v1/produk` (Daftar produk publik berhalaman, filter, dan urut)
- `GET /api/v1/produk/{slug}` (Detail produk aktif)
- `GET /api/v1/toko/{slug}` (Profil toko aktif beserta jumlah produk)

## Aturan Bisnis yang Ditegakkan
- **LP-1**: Seluruh harga produk dan ongkir disimpan dalam bilangan bulat rupiah (`harga_rupiah`, `ongkir_rupiah`).
- **LP-3**: Satu pengguna memiliki paling banyak satu toko (ditegakkan via unique constraint pada `toko.pemilik_id`). Toko baru berstatus menunggu (`menunggu`).
- **LP-4**: Produk hanya tampil dan dapat dibeli bila berstatus aktif (`aktif`), tokonya aktif (`aktif`), dan stoknya mencukupi. Didefinisikan secara tunggal pada `Produk::scopeTampil()`.

## Yang Dibutuhkan dari Domain Lain
- **D1 (Identitas)**: Tabel `pengguna` untuk relasi `pemilik_id` pada tabel `toko`.
- **D3 (Uang)**: Value object `App\Domain\Uang` untuk formatting rupiah pada resource respons.
- **D5 (Kualitas)**: Trait `ResponsApi` untuk format amplop respons standar (`sukses`, `berhalaman`).

## Yang Disediakan untuk Domain Lain
- Model `Produk` dan `Toko` beserta factory untuk pengujian domain lain.
- Scope `Produk::query()->tampil()` sebagai satu-satunya pintu gerbang produk yang sah untuk belanja (D3).

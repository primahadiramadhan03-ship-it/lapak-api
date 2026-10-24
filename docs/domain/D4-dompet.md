# D4 — Pembayaran & Saldo

## Pemilik
Risa (akun GitHub: @risayulia)

## Tanggung Jawab
Domain ini menjawab pertanyaan: **Di mana setiap rupiah berada saat ini?**

## Tabel yang Dikelola
| Tabel | Fungsi |
|-------|--------|
| `dompet` | Menyimpan saldo setiap entitas (eksternal, escrow, pendapatan, penjual) |
| `mutasi_saldo` | Catatan perpindahan uang (tidak boleh diubah/dihapus) |

## Endpoint yang Akan Dibuat
- Riwayat mutasi dompet
- Saldo pengguna
- Pencairan saldo

## Aturan Bisnis yang Ditegakkan
- **LP-1**: Seluruh nilai uang adalah bilangan bulat rupiah.
- **LP-8**: Komisi marketplace 5% dari subtotal produk.
- **LP-12**: Dana pembeli ditahan di escrow.
- **LP-13**: Saldo hanya berubah lewat pemindahan antardompet yang tercatat sebagai sepasang mutasi berjumlah nol. Mutasi tidak diubah dan tidak dihapus. Hanya dompet eksternal yang boleh negatif.
- **LP-16**: Pencairan saldo minimal Rp 50.000.

## Yang Dibutuhkan dari Domain Lain
- **D1 (Identitas)**: tabel `pengguna` untuk relasi `pengguna_id` pada dompet penjual.
- **D3 (Uang)**: value object `Uang` untuk perhitungan.
- **D5 (Kualitas)**: amplop respons `ResponsApi` dan induk galat `GalatDomain`.

## Yang Disediakan untuk Domain Lain
- Service `BukuBesar` untuk memindahkan saldo antar dompet.
- Perintah `lapak:periksa-buku` untuk verifikasi keseimbangan buku besar.
- Seeder `DompetSeeder` untuk membuat 3 dompet sistem.

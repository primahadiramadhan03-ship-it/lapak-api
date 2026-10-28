# ATURAN BISNIS — Lapak Marketplace

Berikut adalah 17 aturan bisnis berkode yang dirujuk dari kode, pesan galat, kasus uji, dan judul PR.

| Kode | Aturan | Ditegakkan di |
|---|---|---|
| LP-1 | Seluruh harga, saldo, dan biaya disimpan sebagai bilangan bulat rupiah. Tidak ada float di lapisan mana pun. | `app/Domain/Uang.php`, migrasi `harga_rupiah`, `ongkir_rupiah` |
| LP-2 | Operasi uang bersifat immutable: setiap operasi menghasilkan objek baru, nilai asli tidak berubah. | `app/Domain/Uang.php` |
| LP-3 | Satu pengguna paling banyak satu toko (`pemilik_id` unik). | migrasi `toko` |
| LP-4 | Produk tampil di katalog hanya jika berstatus aktif DAN tokonya aktif. Produk draf dan toko menunggu tidak bocor ke publik. | `Produk::scopeTampil()`, `KatalogController` |
| LP-5 | Normalisasi surel: duplikat ditolak tanpa memandang huruf besar/kecil. | `Penguna` model, `RegistrasiController` |
| LP-6 | Angka klien diabaikan pada simulasi belanja; total dihitung ulang dari server. | (D3 — Brili) |
| LP-7 | Kata sandi hanya disimpan sebagai hash, tidak pernah dikirim ke klien. | `RegistrasiController`, `PenggunaResource` |
| LP-8 | Kolom siluman (`id`, `status`) diabaikan saat registrasi publik. | `RegistrasiRequest` |
| LP-9 | Setiap mutasi dompet harus tercatat; tidak ada penghapusan atau pembaruan langsung. | (D4 — Risa) |
| LP-10 | Saldo tidak boleh negatif setelah transaksi. | (D4 — Risa) |
| LP-11 | Permintaan non-JSON ditolak dengan respons JSON konsisten. | (D5 — Jaya) |
| LP-12 | Header `X-Request-Id` ditambahkan pada setiap respons API. | (D5 — Jaya) |
| LP-13 | Pesan galat mengikuti struktur respons API yang terstandardisasi. | (D5 — Jaya) |
| LP-14 | Semua cabang rute API dimuat dinamis dari `routes/api.php`. | `routes/api.php` |
| LP-15 | Migrasi mengikuti pola penomoran `2026_10_SS_DNNNNN_nama.php`. | Semua migrasi |
| LP-16 | Berkas bersama (rute, seeder, bootstrap) harus ditinjau sesuai CODEOWNERS. | `.github/CODEOWNERS` |
| LP-17 | Log AI wajib diisi setiap anggota atau ditulis "tidak memakai AI pada sesi ini". | `docs/log-ai/<nama>.md` |

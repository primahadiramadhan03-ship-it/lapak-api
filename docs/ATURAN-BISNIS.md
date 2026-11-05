# ATURAN BISNIS — Lapak Marketplace

Berikut adalah aturan bisnis berkode yang dirujuk dari kode, pesan galat, kasus uji, dan judul PR.

| Kode | Aturan | Ditegakkan di |
|---|---|---|
| LP-1 | Seluruh harga, saldo, dan biaya disimpan sebagai bilangan bulat rupiah. Tidak ada float di lapisan mana pun. | `app/Domain/Uang.php`, migrasi `harga_rupiah`, `ongkir_rupiah` |
| LP-2 | Operasi uang bersifat immutable: setiap operasi menghasilkan objek baru, nilai asli tidak berubah. | `app/Domain/Uang.php` |
| LP-3 | Satu pengguna paling banyak satu toko (`pemilik_id` unik). | Sebagian: unique pada `toko.pemilik_id`, `TokoSayaController::store` |
| LP-4 | Produk tampil di katalog hanya jika berstatus aktif DAN tokonya aktif. Produk draf dan toko menunggu tidak bocor ke publik. | `Produk::scopeTampil()`, `KatalogController` |
| LP-5 | Penjual tidak membeli dari tokonya sendiri. | Sebagian: `PenyusunBelanja::pilah`, `LayananKeranjang::aturButir` |
| LP-6 | Angka klien diabaikan pada simulasi belanja dan keranjang; harga dan total dihitung ulang dari server. | Sebagian: `SimulasiBelanjaRequest`, `PenyusunBelanja`, `LayananKeranjang::lihat` |
| LP-7 | Kata sandi hanya disimpan sebagai hash, tidak pernah dikirim ke klien. | `RegistrasiController`, `PenggunaResource` |
| LP-8 | Kolom siluman (`id`, `status`) diabaikan saat registrasi publik. | `RegistrasiRequest` |
| LP-9 | Setiap mutasi dompet harus tercatat; tidak ada penghapusan atau pembaruan langsung. | `app/Services/BukuBesar.php`, model `MutasiSaldo`, `PeriksaBuku` |
| LP-10 | Saldo tidak boleh negatif setelah transaksi. | `BukuBesar`, exception `SaldoTidakCukup`, pengujian domain Dompet |
| LP-11 | Permintaan non-JSON ditolak dengan respons JSON konsisten. | Middleware `PaksaJson`, `bootstrap/app.php` |
| LP-12 | Header `X-Request-Id` ditambahkan pada setiap respons API. | Middleware `IdPermintaan`, `bootstrap/app.php` |
| LP-13 | Pesan galat mengikuti struktur respons API yang terstandardisasi. | `ResponsApi`, `PenanganGalatApi`, `bootstrap/app.php` |
| LP-14 | Kunci sesi berbentuk awalan, pengenal, dan rahasia; kedaluwarsa 7 hari, menganggur 2 jam. | `KunciSesi`, `PenerbitSesi`, `Sesi::scopeBerlaku`, `lapak:pangkas-sesi` |
| LP-15 | Tindakan peka menuntut konfirmasi ulang kata sandi (sesi segar 10 menit). | Sebagian: `SesiSegar`, `SesiController::konfirmasi`; dipakai pada cabut sesi dan riwayat mutasi |
| LP-16 | Berkas bersama (rute, seeder, bootstrap) harus ditinjau sesuai CODEOWNERS. | `.github/CODEOWNERS` |
| LP-17 | Log AI wajib diisi setiap anggota atau ditulis "tidak memakai AI pada sesi ini". | `docs/log-ai/` |

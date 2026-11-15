# ATURAN BISNIS — Lapak Marketplace

Berikut adalah aturan bisnis berkode yang dirujuk dari kode, pesan galat, kasus uji, dan judul PR.

| Kode | Aturan | Ditegakkan di |
|---|---|---|
| LP-1 | Seluruh harga, saldo, dan biaya disimpan sebagai bilangan bulat rupiah. Tidak ada float di lapisan mana pun. | `app/Domain/Uang.php`, migrasi `harga_rupiah`, `ongkir_rupiah` |
| LP-2 | Perubahan peran pengguna hanya melalui PengelolaPeran. | `NamaPeran::izinBawaan`, `PemeriksaIzin`, middleware izin, `PengelolaPeran`, `MatriksOtorisasiTest` |
| LP-3 | Satu pengguna paling banyak satu toko (`pemilik_id` unik). | `unique` pada `toko.pemilik_id`, izin `toko.buka`, `ModerasiToko`, `TokoPolicy` |
| LP-4 | Produk tampil di katalog hanya jika berstatus aktif DAN tokonya aktif. Stok dijaga atomik lewat penguncian baris (`lockForUpdate`). | `Produk::scopeTampil()`, `KatalogController`, `PenjagaStok`, `LayananCheckout`, `RebutanStok`, `PenyesuaianStok` |
| LP-5 | Penjual tidak membeli dari tokonya sendiri. | `PenyusunBelanja::pilah`, `LayananKeranjang::aturButir`, `LayananCheckout` |
| LP-6 | Angka klien diabaikan pada simulasi belanja, keranjang, dan checkout; harga dan total dihitung ulang dari server. | `SimulasiBelanjaRequest`, `PenyusunBelanja`, `LayananKeranjang::lihat`, `LayananCheckout` |
| LP-7 | Hubungan 1:1 antara checkout dan pembayaran; arah foreign key menunjuk ke checkout. Kata sandi hanya disimpan sebagai hash. | `buat_tabel_pembayaran.php`, model `Pembayaran`, `Checkout`, `LayananPembayaran`, `RegistrasiController` |
| LP-8 | Komisi dihitung dari subtotal saja, ongkir utuh untuk penjual. Kolom siluman diabaikan saat registrasi. | `PenghitungBelanja`, `PenyusunBelanja`, `RegistrasiRequest` |
| LP-9 | Setiap mutasi dompet harus tercatat. Format penomoran pesanan unik berbasis tanggal WIB (`YYYYMMDD-{id}`). | `app/Services/BukuBesar.php`, `MutasiSaldo`, `PeriksaBuku`, `PenomorPesanan` |
| LP-10 | Perpindahan status pesanan diputuskan di satu tempat; pembatalan otomatis 24 jam dengan pemulihan stok urut ID produk. | `AturanTransisi`, `MesinStatusPesanan`, `PembatalanOtomatis`, `BatalkanKedaluwarsa` |
| LP-11 | Idempotensi mutasi krusial via `Idempotency-Key`; komit atomik catatan kunci dan efek transaksinya. | Middleware `Idempoten`, `KunciIdempotensi`, `CheckoutController`, `ProdukSayaController@stok`, `RuteIdempotenTest`, `KunciGanda` |
| LP-12 | Webhook pembayaran diverifikasi tanda tangan HMAC-SHA256 mentah, toleransi replay attack, dan menahan dana di escrow tepat satu kali. | `TandaTanganGateway`, `WebhookPembayaranController`, `LayananPembayaran::tahanDana`, `WebhookGanda` |
| LP-13 | Idempotensi pemrosesan webhook (pembayaran kembar hanya menghasilkan satu kali mutasi escrow dan respons 200 `sudah_diproses`). | `LayananPembayaran::terima`, `WebhookPembayaranController` |
| LP-14 | Kunci sesi berbentuk awalan, pengenal, dan rahasia; kedaluwarsa 7 hari, menganggur 2 jam. | `KunciSesi`, `PenerbitSesi`, `Sesi::scopeBerlaku`, `lapak:pangkas-sesi` |
| LP-15 | Tindakan peka menuntut konfirmasi ulang kata sandi (sesi segar 10 menit) atau checkout bernilai besar. | `SesiSegar`, `SesiController::konfirmasi`, `BukuBesarController`, `CheckoutController` |
| LP-16 | Berkas bersama (rute, seeder, bootstrap) harus ditinjau sesuai CODEOWNERS. | `.github/CODEOWNERS` |
| LP-17 | Log AI wajib diisi setiap anggota atau ditulis "tidak memakai AI pada sesi ini". | `docs/log-ai/` |
| LP-18 | Dana keluar dari escrow hanya lewat LayananDana; pencairan saldo penjual berpenahan minimal Rp 50.000 dengan putusan orang kedua (pengaju bukan penyetuju). | `LayananDana`, `LayananPencairan`, `PencairanController`, `PencairanTest`, `Balapan/PencairanGanda` |
| LP-19 | Log audit berantai hash (append-only); hanya PencatatAudit yang menulis log_audit dan setiap baris memuat hash baris sebelumnya. | `PencatatAudit`, `PemeriksaRantaiAudit`, `VerifikasiAuditCommand`, `HanyaPencatatAuditTest`, `VerifikasiAuditTest` |

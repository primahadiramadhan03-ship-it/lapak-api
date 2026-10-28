# Aturan Bisnis — Lapak

Tujuh belas aturan berikut adalah kontrak domain Lapak. Setiap aturan berkode agar dapat dirujuk dari kode, pesan galat, kasus uji, dan judul Pull Request.

| Kode | Aturan | Ditegakkan di |
|---|---|---|
| LP-1 | Seluruh nilai uang adalah bilangan bulat rupiah. Tipe pecahan dilarang di kolom, kode, dan JSON. | `app/Domain/Uang.php`, migrasi `harga_rupiah`, `ongkir_rupiah` |
| LP-2 | Kewenangan ditentukan izin yang melekat pada peran, bukan nama peran. Satu akun boleh memegang lebih dari satu peran. | (D5 — Jaya) |
| LP-3 | Satu pengguna memiliki paling banyak satu toko. Toko baru berstatus menunggu sampai disetujui admin. | migrasi `toko` (`pemilik_id` unique) |
| LP-4 | Produk hanya tampil dan dapat dibeli bila berstatus aktif, tokonya aktif, dan stoknya mencukupi. Stok tidak pernah negatif. | `Produk::scopeTampil()`, `KatalogController` |
| LP-5 | Penjual tidak dapat membeli produk dari tokonya sendiri. | (D3 — Briliant) |
| LP-6 | Harga, ongkir, dan total dihitung server dari basis data. Angka kiriman klien diabaikan. Harga dibekukan pada butir pesanan saat checkout. | (D3 — Briliant) |
| LP-7 | Satu checkout menghasilkan satu pesanan per toko dan dibayar dengan satu pembayaran. | (D3 — Briliant) |
| LP-8 | Komisi marketplace 5% dari subtotal produk, dibulatkan setengah ke atas, dipotong dari bagian penjual saat dana dilepas. Ongkir sepenuhnya milik penjual. | (D4 — Risa) |
| LP-9 | Nomor pesanan berformat LPK-YYYYMMDD-NNNNNN dan unik seumur hidup sistem. | (D4 — Risa) |
| LP-10 | Status pesanan bergerak menunggu pembayaran, dibayar, dikemas, dikirim, selesai. Pembatalan hanya sebelum dikirim. Pesanan yang tidak dibayar dalam 24 jam batal otomatis dan stoknya kembali. | (D4 — Risa) |
| LP-11 | Setiap permintaan yang mengubah uang atau stok wajib membawa Idempotency-Key. Kunci yang sama menghasilkan respons yang sama dan efek satu kali. | (D5 — Jaya) |
| LP-12 | Dana pembeli ditahan di escrow dan dilepas ke penjual setelah pembeli mengonfirmasi terima, atau otomatis 3 hari setelah dikirim. | (D4 — Risa) |
| LP-13 | Saldo hanya berubah lewat pemindahan antardompet yang tercatat sebagai sepasang mutasi berjumlah nol. Mutasi tidak diubah dan tidak dihapus. Hanya dompet eksternal yang boleh negatif. | (D4 — Risa) |
| LP-14 | Kunci sesi ditampilkan satu kali dan disimpan sebagai hash. Sesi berlaku paling lama 7 hari, gugur setelah 2 jam tidak dipakai, dan dapat dicabut per perangkat. | (D1 — Prima) |
| LP-15 | Tindakan sensitif menuntut sesi segar: kata sandi dikonfirmasi ulang dalam 10 menit terakhir. | (D1 — Prima) |
| LP-16 | Pencairan saldo minimal Rp 50.000, disetujui peran keuangan, dan penyetuju tidak boleh orang yang sama dengan pengaju. | (D4 — Risa) |
| LP-17 | Setiap tindakan sensitif tercatat pada log audit yang hanya dapat ditambah dan berantai hash. | (D5 — Jaya) |

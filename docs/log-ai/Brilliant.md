# Log Penggunaan AI — Brilliant

**Alat yang dipakai**: Google Gemini (AI Chat Assistant)

## Sesi 1
**Bagian yang dibantu**: 
  - Berdiskusi mengenai struktur *value object* `Uang.php` dan logika pemecahan belanja di `PenghitungBelanja.php`.
  - Memahami cara penulisan kasus uji (*test cases*) menggunakan Pest.

**Cara memverifikasi hasil**: 
  - Menjalankan perintah `./vendor/bin/pest` secara mandiri di terminal untuk memastikan pengujian bernilai hijau (*passed*).
  - Membaca dan memeriksa ulang kode agar sesuai dengan aturan bisnis Lapak.

## Sesi 2
**Bagian yang dibantu**:
  - Berdiskusi mengenai penegakan aturan bisnis LP-5 (larangan membeli produk toko sendiri) pada pemilah belanja.
  - Memahami perancangan kasus uji keranjang pada `KeranjangTest.php` untuk memastikan sifat idempoten pada endpoint `PUT /api/v1/keranjang/butir/{produkId}`.

**Cara memverifikasi hasil**:
  - Menjalankan `./vendor/bin/pint --test` untuk memastikan kepatuhan standar format kode tim.
  - Menjalankan `php artisan migrate:fresh --seed` dan `./vendor/bin/pest` secara mandiri di terminal untuk memastikan seluruh pengujian hijau.
  - Memeriksa bahwa respons galat LP-5 mengembalikan kode HTTP 422 dan amplop galat yang sesuai kontrak.

 ## Sesi 3
**Bagian yang dibantu**:
  - Berdiskusi dan memvalidasi alternatif desain state machine pada `AturanTransisi` agar seluruh kemungkinan transisi status pesanan selaras dengan aturan bisnis modul.
  - Mengklarifikasi urutan prioritas middleware di Laravel (`PastikanIzin` vs `SubstituteBindings`) untuk memastikan otorisasi dievaluasi sebelum penautan model.
  - Mengonsultasikan referensi penanganan asersi tipe data pada Pest saat membandingkan nilai string array dari `PemeriksaIzin::daftar` dengan objek enum `NamaIzin`.

**Cara memverifikasi hasil**:
  - Menjalankan `./vendor/bin/pest` dan `./vendor/bin/pint --test` secara mandiri di terminal pada setiap tahap integrasi cabang hingga mencapai 266 kasus uji lulus.
  - Memeriksa langsung *git diff* dan menyelesaikan konflik penggabungan cabang secara manual di terminal untuk menjaga keutuhan kode antar-domain.
  - Menguji idempotensi `PeranIzinSeeder` dan memverifikasi perilaku *cache invalidation* pada `PengelolaPeran::cabut` agar izin pengguna terputus secara konsisten.

## Sesi 4
**Bagian yang dibantu**:
  - Merancang skenario balapan `batal-saat-checkout` dan menganalisis kebuntuan (*deadlock*) akibat perbedaan urutan penguncian baris antara proses checkout dan pembatalan pesanan.
  - Memperbaiki pengurutan pengembalian stok pada `LayananPesanan::kembalikanStok()` agar selalu urut nomor produk menaik (menghindari MySQL error 1213 deadlock).
  - Menyusun rincian transisi status pesanan dan penanganan idempotensi checkout.

**Cara memverifikasi hasil**:
  - Menjalankan skenario balapan `php artisan lapak:balapan batal-saat-checkout` pada basis data MySQL InnoDB (12/12 proses lulus tanpa deadlock).
  - Menjalankan `./vendor/bin/pest` pada SQLite dan MySQL untuk memverifikasi seluruh skenario checkout.
  - Memverifikasi format kode dengan `./vendor/bin/pint --test`.

## Sesi 5 — A3: Kontrak Layanan Pesanan, Cap Waktu Proses, dan Pendukung Uji
**Bagian yang dibantu**:
  - Merancang migrasi `2026_10_05_300001_tambah_proses_pada_pesanan.php` untuk menambahkan kolom cap waktu proses (`dikemas_pada`, `dikirim_pada`, `nomor_resi`, `selesai_pada`, `dibatalkan_oleh`) pada tabel pesanan.
  - Mengimplementasikan `LayananPesanan` sebagai pintu tunggal setelah checkout (LP-10, LP-12) yang mengoordinasikan `GembokCheckout`, `MesinStatusPesanan`, `PenjagaStok`, `LayananDana`, dan `PencatatAudit`.
  - Menyelaraskan kontrak audit trail: mencatat `pesanan.diterima` dan `pesanan.selesai_otomatis` serta `pesanan.dibatalkan` di `LayananPesanan`, sementara `dana.dilepas` dan `dana.dikembalikan` dicatat di `LayananDana`.
  - Memutakhirkan `PelepasanSerentak` agar menggunakan pintu tunggal `LayananPesanan::terima()`.
  - Menambahkan *test helper* `PesananUji::dibayar()` dan melengkapi cakupan kasus uji pada `LayananPesananTest`.

**Cara memverifikasi hasil**:
  - Menjalankan `./vendor/bin/pint --test` (seluruh berkas lulus format).
  - Menjalankan `./vendor/bin/pest` di SQLite dan MySQL untuk memastikan seluruh uji hijau.
  - Menjalankan `php artisan lapak:balapan pelepasan-serentak` dan `php artisan lapak:balapan batal-saat-checkout` di MySQL InnoDB.
  - Memverifikasi keutuhan buku besar dengan `php artisan lapak:periksa-buku`.

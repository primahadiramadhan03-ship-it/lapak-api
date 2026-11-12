# Sesi 5 — Escrow, Saldo, Audit, dan Rilis v1.0.0

## Papan Integrasi Pull Request

| PR | Unggahan | Cabang | Penulis | Isi / Pekerjaan | Peninjau | Menunggu | Status |
|---|---|---|---|---|---|---|---|
| #47 | Pembuka | `sesi-5/pembuka-sekar` | Sekar (Jaya) | Papan sesi, daftar periksa rilis, dua anak tangga kunci, penataan dua berkas bersama | Bambang (Prima), Bagas (Syafiq) | - | Siap Review |
| #48 | A1 | `sesi-5/kontrak-audit-sekar` | Sekar (Jaya) | Tabel log audit dan kepala rantai, daftar peristiwa, rantai hash, PencatatAudit versi kontrak | Bambang (Prima), Bagas (Syafiq) | #47 | Belum |
| #49 | A2 | `sesi-5/kontrak-dana-dimas` | Dimas (Risa) | Tabel pencairan, dompet penahan, LayananDana, tagihan dapat dibatalkan | Sekar (Jaya), Bambang (Prima) | #47 | Belum |
| #50 | A3 | `sesi-5/kontrak-pesanan-rani` | Rani (Brili) | Cap waktu proses, LayananPesanan, pendukung uji | Dimas (Risa), Sekar (Jaya) | #48, #49 | Belum |
| #51 | B1 | `sesi-5/jejak-identitas-bambang` | Bambang (Prima) | Kunci lama terdeteksi; jejak masuk, keluar, dan peran | Bagas (Syafiq), Rani (Brili) | #50 | Belum |
| #52 | B2 | `sesi-5/pesanan-toko-bagas` | Bagas (Syafiq) | Izin `pesanan.proses`, lima rute pesanan toko, jejak moderasi | Rani (Brili), Dimas (Risa) | #50 | Belum |
| #53 | B3 | `sesi-5/tindakan-pembeli-rani` | Rani (Brili) | Terima, batal, batal checkout, penyelesaian otomatis, dua skenario | Dimas (Risa), Sekar (Jaya) | #50 | Belum |
| #54 | B4 | `sesi-5/pencairan-dimas` | Dimas (Risa) | Pembayaran terlambat, pencairan, invarian keenam, dua skenario | Sekar (Jaya), Bambang (Prima) | #50 | Belum |
| #55 | B5 | `sesi-5/rantai-audit-sekar` | Sekar (Jaya) | Kunci kepala rantai, verifikasi, pintu auditor, pangkas idempotensi, CI | Bambang (Prima), Bagas (Syafiq) | #51–#54 | Belum |
| #56 | Rilis | `sesi-5/rilis-sekar` | Sekar (Jaya) | Uji penutup, versi 1.0.0, README, papan sesi, tempat penegakan aturan | Bambang (Prima), Bagas (Syafiq) | Temuan kandidat | Belum |
| #57 | P1 | `sesi-5/jejak-stok-bagas` | Bagas (Syafiq) | Penyesuaian stok berjejak: temuan kandidat pertama | Rani (Brili), Dimas (Risa) | Temuan #56 | Belum |
| #58 | Catatan | `sesi-5/catatan-perubahan-bambang` | Bambang (Prima) | `CHANGELOG.md` | Bagas (Syafiq), Rani (Brili) | #56 | Belum |

Ketergantungan: A1 dan A2 saling bebas dan ditulis bersamaan. A3 menunggu keduanya, karena `LayananPesanan` memanggil `LayananDana` dan `PencatatAudit`. Kelima PR Jendela B berangkat dari `main` yang sama setelah A3 masuk.

## Sasaran Sesi 5

1. Menyelesaikan perjalanan uang: pesanan diterima, dana dilepas dari escrow kepada penjual atau dikembalikan kepada pembeli, lalu penjual mencairkannya ke rekening.
2. Menegakkan jejak audit atas setiap tindakan yang menyentuh uang, stok, atau kewenangan, ditambah percobaan masuk, dalam bentuk rantai hash yang dapat diverifikasi.
3. Menjaga empat pintu tunggal: `LayananDana`, `LayananPencairan`, `LayananPesanan`, dan `PencatatAudit`.
4. Merilis versi bernomor `v1.0.0` yang dapat dipasang orang lain, dengan catatan perubahan dan langkah naik versi.

## Jawaban atas Titipan Sesi 4

- Dana yang sudah masuk dan tertahan di escrow kini punya jalan keluar: dilepas, dikembalikan, atau dicairkan.
- Jejak audit berantai hash diaktifkan di atas seluruh transaksi yang sudah ada, tanpa menulis ulang riwayat lama.
- Idempotensi dan tangga kunci baris dari Sesi 4 diperluas: pencairan menempati anak tangga yang sama dengan pembayaran, dan kepala rantai audit menjadi anak tangga terakhir.
- Dua temuan kandidat rilis dicatat sebagai bagian dari proses, bukan disembunyikan: seed izin yang tidak berjalan pada `migrate`, dan peristiwa audit yang belum punya pencatat.

## Tujuh Keputusan Rancangan

1. Dua peninjau untuk setiap PR: dua anggota berikutnya pada lingkar. Peninjau pertama menjalankan cabang, peninjau kedua membaca selisih dengan satu pertanyaan: adakah akibat tindakan ini yang ditulis di luar pintu tunggalnya, atau yang tidak berjejak?
2. Berkas bersama ditata sebelum direbutkan: pemetaan izin satu izin per baris, dan daftar rute POST dipecah satu berkas per domain.
3. Daftar peristiwa adalah kontrak. `PeristiwaAudit` ditulis lengkap pada A1, sebelum satu pun pintunya ada.
4. Uji di cabang sendiri tidak boleh bergantung pada PR rekan. Uji sebuah domain hanya menuntut jejak milik domain itu.
5. Jendela B maju sehari: tenggat merge H+4, H+5 dan H+6 untuk kandidat rilis, verifikasi, perbaikan temuan, catatan perubahan, dan tag.
6. Seluruh tag berbentuk anotasi. `v1.0.0` dan `sesi-5` dipasang pada commit yang sama.
7. Temuan kandidat diperbaiki pemilik domainnya di cabangnya sendiri. Integrator tidak menambal kode domain lain di dalam PR rilis.

## Tabel Dua Peninjau

| Peninjau | Sebagai peninjau pertama (menjalankan) | Sebagai peninjau kedua (membaca selisih) |
|---|---|---|
| Bambang (Prima) | #47, #48, #55, #56 | #49, #54 |
| Bagas (Syafiq) | #51, #58 | #47, #48, #55, #56 |
| Rani (Brili) | #52, #57 | #51, #58 |
| Dimas (Risa) | #50, #53 | #52, #57 |
| Sekar (Jaya) | #49, #54 | #50, #53 |

Peninjau pertama wajib menarik cabang, menjalankan migrasi dan pengujian (SQLite dan MySQL), serta mencoba cara menguji pada deskripsi PR.

## Urutan Jendela A

| Urutan | Pelaku | PR | Pekerjaan | Menunggu |
|---|---|---|---|---|
| 1 | Sekar (Jaya) | #48 | Log audit versi kontrak | #47 |
| 1 | Dimas (Risa) | #49 | Dana dan pencairan versi kontrak | #47 |
| 2 | Rani (Brili) | #50 | Layanan pesanan | #48, #49 |
| 3 | Sekar (Jaya) | - | Merge #47, #48, #49, lalu #50 berurutan | - |
| 4 | Semua | - | Pull `main`, pastikan uji hijau, lalu buat cabang Jendela B | - |

## Urutan Jendela B

| Urutan | Pelaku | PR | Pekerjaan | Tenggat |
|---|---|---|---|---|
| 1 | Bambang (Prima) | #51 | Kunci lama dan jejak identitas | Draf H+2, merge H+4 |
| 2 | Bagas (Syafiq) | #52 | Pintu penjual atas pesanan dan jejak moderasi | Draf H+2, merge H+4 |
| 3 | Rani (Brili) | #53 | Terima, batal, dan penyelesaian otomatis | Draf H+2, merge H+4 |
| 4 | Dimas (Risa) | #54 | Pembayaran terlambat, pencairan, invarian keenam | Draf H+2, merge H+4 |
| 5 | Sekar (Jaya) | #55 | Kunci kepala rantai, verifikasi, dan pintu auditor | Draf H+2, merge H+4 |

Setiap PR membawa baris matriks izin untuk rute barunya, baris daftar POST bila rutenya POST, pencatatan peristiwa di dalam transaksi, dan keluaran `lapak:balapan` dari MySQL bila memindahkan uang. Merge berurutan B1 sampai B5.

## Urutan Jendela Rilis

| Urutan | Pelaku | Pekerjaan |
|---|---|---|
| 1 | Sekar (Jaya) | Tag `v1.0.0-rc.1` pada klon yang bersih |
| 2 | Bambang (Prima) | Verifikasi kandidat pada klon baru, basis data dari nol |
| 3 | Dimas (Risa), Rani (Brili) | Uji naik versi dari `sesi-4` pada basis data `lapak_naik` |
| 4 | Sekar (Jaya) | Uji penutup `PeristiwaTerpakaiTest` |
| 5 | Bagas (Syafiq) | Perbaikan temuan pada #57 |
| 6 | Sekar (Jaya) | Merge #57, lalu tag `v1.0.0-rc.2` |
| 7 | Sekar (Jaya) | Alur sesi lengkap pada `v1.0.0-rc.2` |
| 8 | Bambang (Prima) | `CHANGELOG.md` pada #58 |
| 9 | Sekar (Jaya) | Merge #56 paling akhir |
| 10 | Sekar (Jaya) | Tag `v1.0.0` dan `sesi-5` pada commit yang sama, lalu halaman Release |

## Daftar Peristiwa Audit

Usulan berikut dibawa ke rapat H+0 dan disahkan tim sebelum A1 ditulis. Jumlah tim boleh berbeda.

| Peristiwa | Pemicu | Pencatat |
|---|---|---|
| `masuk` | Masuk berhasil | PemeriksaSesi |
| `keluar` | Sesi dicabut atas permintaan pemiliknya | PemeriksaSesi |
| `masuk.gagal` | Percobaan masuk gagal, surel dicatat, kata sandi tidak pernah | PemeriksaSesi |
| `sesi.kunci_lama_dipakai` | Kunci lama dipakai lagi dan sesinya dicabut | PemeriksaSesi |
| `peran.diberi` | Peran ditambahkan ke pengguna | PengelolaPeran |
| `peran.dicabut` | Peran dicabut dari pengguna | PengelolaPeran |
| `toko.aktif` | Moderasi menyetujui toko | ModerasiToko |
| `toko.ditangguhkan` | Moderasi menangguhkan toko | ModerasiToko |
| `pesanan.diterima` | Pembeli menekan terima | LayananPesanan |
| `pesanan.dibatalkan` | Pembeli membatalkan pesanan | LayananPesanan |
| `pesanan.selesai_otomatis` | Masa tahan lewat tanpa tombol terima | LayananPesanan |
| `dana.dilepas` | Escrow dilepas ke penjual | LayananDana |
| `dana.dikembalikan` | Escrow dikembalikan ke pembeli | LayananDana |
| `pembayaran.terlambat` | Pembayaran tiba setelah tagihan kedaluwarsa | LayananDana |
| `pencairan.diajukan` | Penjual mengajukan pencairan | LayananPencairan |
| `pencairan.disetujui` | Staf keuangan menyetujui pencairan | LayananPencairan |
| `pencairan.ditolak` | Staf keuangan menolak pencairan | LayananPencairan |
| `stok.disesuaikan` | Penjual mengubah stok relatif | ProdukSayaController |

## Angka Uji dan Target

| Titik | Uji | Catatan |
|---|---|---|
| Titik berangkat repo | 380 | Hasil `./vendor/bin/pest` pada `main` sebelum #47 |
| Setelah Jendela A | sekitar 421 | Tambahan dari A1–A3; belum ada rute baru |
| Setelah Jendela B | sekitar 509 | Tambahan dari B1–B5 |
| `v1.0.0` | sekitar 531 | Empat belas skenario balapan lulus, 58 rute |

Modul Hands-On menyebut 393 pada tag `sesi-4` dan 544 pada `v1.0.0`, karena mengasumsikan latihan mandiri Sesi 4 sudah di-merge. Tim ini memakai angka riil repo. Setiap PR tetap wajib melaporkan jumlah uji cabangnya dan jumlahnya pada `main` setelah merge.

## Kode Aturan Bisnis Sesi 5

`docs/ATURAN-BISNIS.md` pada repo ini sudah memakai LP-16 untuk berkas bersama dan LP-17 untuk log AI. Karena itu aturan baru Sesi 5 ditambahkan sebagai:

- LP-18: escrow dan pencairan. Dana hanya keluar dari escrow lewat `LayananDana`, pencairan hanya lewat `LayananPencairan`, minimal pencairan Rp 50.000, dan penyetuju bukan pengaju.
- LP-19: log audit. Hanya `PencatatAudit` yang menulis `log_audit`, baris tidak dapat diubah atau dihapus, dan setiap baris memuat hash baris sebelumnya.

Pemetaan ke modul: LP-16 modul menjadi LP-18 repo, dan LP-17 modul menjadi LP-19 repo.

## Batasan yang Diketahui

Diisi pada H+6, maksimal delapan butir, tanpa baris "sebagian". Kandidat awal:

1. Peristiwa penyesuaian stok baru tercatat setelah PR #57.
2. `migrate` tidak menjalankan `PeranIzinSeeder`; naik versi wajib menjalankan seeder itu.
3. Belum ada pemicu basis data yang menolak perubahan `log_audit`; penjagaan masih di lapisan model.
4. Verifikasi rantai audit berjalan sebagai perintah, belum sebagai jadwal otomatis pada server.
5. Penyelarasan dua tag (`v1.0.0` dan `sesi-5`) masih manual.
6. Rekap sesi bergantung pada Docker pada lingkungan pengembangan tim.
7. Belum ada latihan mandiri Sesi 4 yang masuk; jumlah uji mengikuti angka riil repo.
8. Temuan kandidat lain menyusul bila muncul pada verifikasi klon baru.

## Bukti dan Luaran

- Dua belas PR berstatus merge dengan dua persetujuan masing-masing.
- Tag `v1.0.0` dan `sesi-5` pada commit yang sama, semuanya anotasi.
- `CHANGELOG.md`, `README.md`, dan halaman Release.
- Keluaran `scripts/rekap-sesi.sh 5` ditempel pada catatan penutup papan sesi.
- Laporan sesi 6 sampai 8 halaman.

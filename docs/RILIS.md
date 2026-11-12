# Daftar Periksa Rilis — Lapak

Berlaku sejak Sesi 5. Dokumen ini ditulis pada PR pembuka supaya garis akhirnya terlihat sejak hari pertama.

## A. Sebelum kandidat ditandai

- [ ] Seluruh PR pada papan sesi berstatus `merge`, dan `main` hijau pada kedua tugas CI.
- [ ] `git status` bersih dan `git log origin/main..main` kosong di klon integrator.
- [ ] `git describe --tags` menyebut tag sesi sebelumnya, dan seluruh tag sudah ada di remote.
- [ ] `config/lapak.php` menyebut versi yang akan dirilis.

## B. Verifikasi kandidat

Dikerjakan pada klon BARU dari tag kandidat, bukan pada folder kerja siapa pun.

- [ ] Pasang bersih: `composer install`, `.env` dari `.env.example`, `php artisan migrate:fresh --seed` di MySQL.
- [ ] `./vendor/bin/pint --test` dan `./vendor/bin/pest` hijau, di SQLite dan di MySQL.
- [ ] `php artisan lapak:balapan --semua` lulus di MySQL.
- [ ] `php artisan lapak:periksa-buku` dan `php artisan lapak:verifikasi-audit` lulus setelahnya.
- [ ] Naik versi: basis data yang dibangun dan diisi pada tag rilis SEBELUMNYA dapat dinaikkan dengan `php artisan migrate` (tanpa `fresh`) dan `php artisan db:seed --class=PeranIzinSeeder`. Pesanan yang lahir sebelum naik versi dapat diselesaikan sesudahnya.
- [ ] Uji penutup sesi hijau: tidak ada izin, rute POST, atau peristiwa audit yang menganggur.

Setiap temuan menjadi issue berlabel `rilis`, diperbaiki pemilik domainnya lewat PR biasa dengan dua peninjau. Integrator tidak menambal kode domain lain di dalam PR rilis.

## C. Menjelang tag rilis

- [ ] `CHANGELOG.md` memuat entri versi ini: apa yang bertambah, apa yang berubah bagi klien, dan langkah naik versi bagi pengelola server.
- [ ] `config/lapak.php` menyebut versi yang sama. `RilisTest` membandingkan keduanya.
- [ ] `README.md` dapat diikuti orang yang belum pernah melihat repositori ini.
- [ ] Papan sesi terisi, dan `docs/ATURAN-BISNIS.md` menyebut tempat penegakan setiap aturan.
- [ ] Tag kandidat dan tag rilis berbentuk anotasi, dan `v1.0.0` serta `sesi-5` dipasang pada commit yang sama.
- [ ] Halaman Release dibuat dari tag `v1.0.0` dan memuat catatan perubahan.

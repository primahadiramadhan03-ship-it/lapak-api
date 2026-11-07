# D5 — Otorisasi, Audit, dan Kualitas

- Pemilik: Jaya
- Sesi: 1
- Pertanyaan yang dijawab: siapa boleh melakukan apa, siapa sudah melakukan
  apa, dan apakah `main` layak dipercaya?

## 1. Berkas yang dimiliki

| Berkas | Fungsi |
|---|---|
| `app/Http/Concerns/ResponsApi.php` | Amplop respons seluruh domain |
| `app/Exceptions/GalatDomain.php` | Induk pelanggaran aturan bisnis |
| `app/Exceptions/PenanganGalatApi.php` | Satu pintu render galat JSON |
| `app/Http/Middleware/PaksaJson.php` | Memaksa `Accept: application/json` |
| `app/Http/Middleware/IdPermintaan.php` | Membuat header `X-Request-Id` |
| `bootstrap/app.php` | Pendaftaran middleware dan penangan galat |
| `app/Providers/AppServiceProvider.php` | Mode ketat Eloquent |
| `routes/api.php` | Pemuat rute per domain (tidak disunting setelah fondasi) |
| `routes/api/sistem.php` | Rute `GET /health` |
| `config/lapak.php` | Kebijakan komisi, batas bayar, dan tahan dana |
| `.github/workflows/ci.yml` | Gerbang kualitas pada setiap PR |
| `tests/Feature/Sistem/` | Uji kontrak galat, uji arsitektur, dan uji matriks otorisasi |
| `app/Enums/NamaPeran.php` | Daftar peran dan matriks izin yang disepakati |
| `app/Enums/NamaIzin.php` | Daftar izin beserta keterangannya |
| `app/Models/Peran.php` | Model peran dan relasi ke pengguna serta izin |
| `app/Models/Izin.php` | Model izin dan relasi ke peran |
| `app/Services/PemeriksaIzin.php` | Menjawab apakah pengguna memegang suatu izin |
| `app/Http/Middleware/PastikanIzin.php` | Middleware alias `izin:<nama>` |
| `app/Console/Commands/MatriksIzin.php` | Perintah `lapak:matriks-izin` |
| `database/seeders/PeranIzinSeeder.php` | Seeder peran, izin, dan pivot yang idempotent |
| `tests/Feature/Sistem/matriks/` | Matriks rute per domain untuk pengujian otorisasi |

## 2. Tabel yang dimiliki

Tabel D5 mulai berlaku pada Sesi 3:

| Sesi | Tabel |
|---|---|
| 3 | `peran`, `izin`, `pengguna_peran`, `peran_izin` |
| 4 | `kunci_idempotensi` |
| 5 | `log_audit` |

## 3. Endpoint

| Metode | Alamat | Keterangan |
|---|---|---|
| GET | `/api/v1/health` | Status aplikasi dan keterjangkauan basis data |

## 4. Yang disediakan untuk domain lain

- `$this->sukses($data, $status, $meta)` dan
  `$this->berhalaman($halaman, $resource)` melalui trait `ResponsApi`.
- `GalatDomain($kode, $pesan, $statusHttp, $rincian)` untuk pelanggaran aturan bisnis.
- Bentuk galat JSON yang seragam, termasuk 404 tanpa header `Accept`.
- Header `X-Request-Id` pada setiap respons.
- Middleware `izin:<nama>` untuk menjaga rute yang menuntut izin tertentu.
- Perintah `php artisan lapak:matriks-izin` untuk menampilkan matriks peran dan izin.
- Gerbang CI: Pint, migrasi + seeder dari basis data kosong, dan Pest.

## 5. Yang dibutuhkan dari domain lain

- Gunakan `ResponsApi`; jangan merakit JSON sendiri di controller.
- Lempar `GalatDomain` untuk pelanggaran aturan bisnis, bukan `response()->json()`.
- Jangan menyunting `bootstrap/app.php`, `AppServiceProvider.php`, `routes/api.php`,
  `config/lapak.php`, atau `.github/` tanpa sepengetahuan pemilik D5.
- Setelah mode ketat aktif, muat relasi dengan `with()` dan panggil `refresh()`
  setelah `create()` bila kolom diisi nilai bawaan basis data.

## 6. Aturan bisnis yang ditegakkan

| Kode | Penegakan |
|---|---|
| LP-1 | Uji arsitektur memindai migration dan menolak `float`/`double`/`decimal` |
| LP-11 | Rencana Sesi 4: middleware idempoten dan tabel `kunci_idempotensi` |
| LP-17 | Rencana Sesi 5: `log_audit` berantai hash dan perintah verifikasi |

## 7. Batas domain

D5 tidak memiliki tabel bisnis dan tidak menulis controller milik domain lain.
Perubahan pada berkas bersama dilakukan hanya oleh pemilik D5 atau atas tinjauannya.

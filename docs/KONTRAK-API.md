# Kontrak API Lapak

Dokumen ini mengunci bentuk respons yang dipakai seluruh domain.
Mengubah bentuk di sini berarti mengubah kontrak tim.

- Versi: 0.1.0
- Base path: `/api/v1`
- Format: JSON (`Content-Type: application/json`)

## 1. Amplop respons

### Sukses

```json
{
  "sukses": true,
  "data": {},
  "meta": null
}
```

`meta` hanya diisi pada respons berhalaman:

```json
{
  "sukses": true,
  "data": [],
  "meta": {
    "halaman": 1,
    "per_halaman": 12,
    "total": 10,
    "halaman_terakhir": 1
  }
}
```

### Galat

```json
{
  "sukses": false,
  "galat": {
    "kode": "TIDAK_DITEMUKAN",
    "pesan": "Sumber daya tidak ditemukan.",
    "rincian": null
  }
}
```

`rincian` berisi data tambahan (misalnya kesalahan validasi per kolom) atau `null` bila tidak ada.

## 2. Tabel kode galat

| Kode | HTTP | Kapan muncul |
|---|---|---|
| VALIDASI | 422 | Data kiriman tidak memenuhi aturan validasi |
| BELUM_MASUK | 401 | Sesi tidak ada atau tidak berlaku |
| KREDENSIAL_SALAH | 401 | Surel atau kata sandi tidak sesuai (LP-14) |
| DITOLAK | 403 | Pengguna tidak berwenang |
| SESI_TIDAK_SEGAR | 403 | Sesi ada tapi kata sandi harus dikonfirmasi ulang (LP-15) |
| TIDAK_DITEMUKAN | 404 | Sumber daya tidak ada atau sengaja disembunyikan |
| METODE_TIDAK_DIIZINKAN | 405 | Metode HTTP salah |
| TERLALU_SERING | 429 | Melebihi batas laju permintaan |
| GALAT_SERVER | 500 | Galat tak terduga; rincian tidak pernah dikirim ke klien |
| TIDAK_SEHAT | 503 | Basis data tidak terjangkau |

Pelanggaran aturan bisnis memakai kode aturannya sendiri, misalnya LP-13,
dan dilempar sebagai `App\Exceptions\GalatDomain`.

## 3. Header X-Request-Id

Setiap respons API membawa header `X-Request-Id`. Nilainya selalu dibuat
server (UUID), bukan diambil dari klien. Nilai yang sama dicatat pada log
agar laporan pengguna dapat ditelusuri.

## 4. Aturan uang (LP-1)

- Seluruh nilai uang adalah bilangan bulat rupiah.
- Tipe float, double, dan decimal dilarang pada kolom, kode, maupun JSON.
- Perhitungan dilakukan di server; angka kiriman klien diabaikan (LP-6).
- Pembulatan komisi memakai pembulatan setengah ke atas.

## 5. Aturan waktu

- Waktu dikirim dalam format ISO 8601, misalnya `2026-10-23T09:30:00+07:00`.
- Perhitungan batas waktu memakai zona waktu aplikasi (Asia/Jakarta).

## 6. Paginasi

Parameter: `halaman` (default 1) dan `per_halaman` (default 12, maksimum 60).
Nilai di luar batas dipotong ke batas terdekat, bukan menghasilkan galat.

## 7. Larangan

- Controller tidak merakit respons galat sendiri; lempar `GalatDomain`
  atau biarkan `PenanganGalatApi` yang menanganinya.
- Kata sandi dan hash-nya tidak boleh muncul pada respons mana pun.
- Pengenal internal yang tidak diperlukan klien tidak dikirim.

---

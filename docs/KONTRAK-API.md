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

## 8. Keranjang Belanja

### GET /api/v1/keranjang
Melihat isi keranjang belanja milik pengguna yang sedang masuk. Butir yang tidak lagi tersedia atau stoknya kurang dipisahkan ke dalam array `tidak_tersedia`.

Header:
- `Authorization: Bearer <kunci>`

Respons sukses (200):
```json
{
  "sukses": true,
  "data": {
    "pesanan": [],
    "total_bayar": {
      "rupiah": 0,
      "tampilan": "Rp 0"
    },
    "tidak_tersedia": []
  },
  "meta": null
}
```

---

## Otorisasi

Autentikasi menjawab "siapa yang masuk" melalui `auth:sesi`. Otorisasi menjawab
"siapa boleh melakukan apa" melalui middleware `izin:<nama>`. Keduanya dipakai
berurutan pada rute yang tidak terbuka untuk publik.

### Peran dan izin

| Peran | Izin bawaan |
|---|---|
| `pembeli` | `keranjang.kelola`, `toko.buka`, `pesanan.buat` |
| `penjual` | `produk.kelola`, `dompet.lihat` |
| `admin` | `toko.moderasi`, `peran.kelola` |
| `keuangan` | `buku-besar.lihat` |
| `auditor` | `buku-besar.lihat` |

Nilai `*` berarti semua pengguna yang sudah masuk, bukan pengunjung tanpa sesi.

### Middleware `izin:<nama>`

Rute menjaga izin dengan menuliskan nama izin sebagai parameter middleware:

```php
Route::middleware(['auth:sesi', 'izin:toko.moderasi'])
    ->get('/contoh', ...);
```

`PastikanIzin` dijalankan sebelum `SubstituteBindings`, sehingga pengecekan izin
tidak menunggu route model binding mencari objek. Pengguna yang tidak memegang
izin menerima respons `403` dengan kode `DITOLAK`.

### Matriks rute

Setiap endpoint yang dilindungi `auth:sesi` wajib terdaftar pada salah satu
berkas `tests/Feature/Sistem/matriks/{domain}.php`. Bentuk berkasnya:

```php
return [
    'GET /api/v1/contoh' => '*',
];
```

Nilai boleh berupa `*` atau nama izin. Pada A1 seluruh nilai masih `*`; Jendela B
mengganti nilai tersebut sesuai izin domainnya. Uji `MatriksOtorisasiTest`
memastikan tidak ada rute terlindung yang terlewat dari matriks.

---

## Idempotensi (LP-11)

Setiap mutasi krusial (seperti checkout dan webhook pembayaran) yang membawa header `Idempotency-Key`
dijamin bersifat idempoten untuk mencegah eksekusi ganda (*double charge* atau *duplicate order*).

### Header `Idempotency-Key`

Klien menyertakan header:
- `Idempotency-Key: <kunci-unik>` (misalnya format UUID atau string alfanumerik hingga 100 karakter).

### Mekanisme & Transaksi

1. **Replay Respons**: Bila permintaan dengan kunci yang sama dan payload yang identik dikirimkan kembali, sistem langsung mengembalikan potret respons yang tersimpan dengan header `Idempotency-Replayed: true` tanpa menjalankan ulang mutasi di controller maupun basis data.
2. **Deteksi Konflik**: Bila kunci yang sama digunakan untuk payload atau rute yang berbeda, sistem menolak permintaan dengan status HTTP `409 Conflict` dan kode galat `LP-11`.
3. **Komit Satu Transaksi**: Catatan pada tabel `kunci_idempotensi` dicatat sebelum anak tangga pertama dan dikomit bersama dengan efek transaksi dalam satu blok transaksi database atomik. Jika transaksi gagal, kunci tidak akan tersimpan (rollback).
4. **Middleware `idempoten`**: Disediakan alias `'idempoten'` (opsional: memeriksa kunci bila ada) atau `'idempoten:wajib'` (menolak dengan `422 Unprocessable Entity` bila header absen).


---

## Webhook pembayaran

- `POST /webhook/pembayaran` adalah satu-satunya rute tulis tanpa kunci sesi. Pemanggilnya
  gerbang pembayaran. Pembeli tidak memiliki pintu apa pun untuk menandai tagihannya lunas.
- Gerbang mengirim header `X-Gateway-Signature: t=<detik unix>,v1=<tanda>`, dengan `<tanda>`
  berupa HMAC-SHA256 atas teks `<t>.<badan mentah>` memakai rahasia bersama `LAPAK_GATEWAY_RAHASIA`.
- Tanpa header, rahasia salah, badan diubah, atau selisih waktu lebih dari 5 menit: jawabannya
  selalu 401 `TANDA_TANGAN_SALAH` yang sama. Rahasia yang kosong menolak seluruh webhook.
- Badan: `id_peristiwa`, `kode_pembayaran`, `jumlah_rupiah`. Jumlah dicocokkan dengan tagihan di
  basis data; selisih satu rupiah pun dijawab 422 `JUMLAH_TIDAK_COCOK` dan tidak melunasi apa pun.
- Kabar yang sama boleh tiba berkali-kali. Yang pertama dijawab `"hasil": "dilunasi"`, sisanya
  `"hasil": "sudah_diproses"`, dan uangnya dibukukan satu kali.

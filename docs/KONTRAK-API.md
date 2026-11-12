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

## Log audit (LP-19)

Setiap tindakan yang menyentuh uang, stok, atau kewenangan meninggalkan satu baris pada `log_audit`.
Daftar peristiwanya ada di `App\Enums\PeristiwaAudit` dan hanya ditulis di berkas itu.

### Satu pintu penulisan

Hanya `PencatatAudit::catat()` yang menulis tabel ini. Tanda tangannya:

```php
catat(
    PeristiwaAudit $peristiwa,
    ?Pengguna $pelaku = null,
    ?string $sasaran = null,
    array $rincian = [],
): LogAudit
```

- `pelaku` diisi `null` bila pelakunya sistem atau gerbang pembayaran.
- `sasaran` berbentuk `jenis:pengenal`, misalnya `pesanan:LPK-20261027-000412` atau `pengguna:8`.
- `rincian` hanya boleh memuat teks, bilangan bulat, boolean, null, dan larik. Kata kunci
  `kata_sandi`, `kunci`, `hash_kunci`, `rahasia`, dan `tanda_tangan` ditolak di tingkat mana pun
  dengan `LogicException`, karena rahasia yang telanjur masuk tidak dapat dihapus tanpa memutus rantai.

Dua aturan pemakaian bagi setiap domain:

1. `catat()` dipanggil di dalam transaksi yang sama dengan tindakannya, sehingga tindakan dan
   jejaknya tersimpan bersama atau batal bersama.
2. `catat()` adalah langkah terakhir sebelum transaksi selesai. Tidak ada kunci baris lain yang
   diambil sesudahnya (`docs/PETA-SKEMA.md`, anak tangga terakhir).

### Bentuk rantai

```text
hash baris = SHA-256( hash baris sebelumnya + "\n" + isi baris dalam bentuk kanonik )
```

Isi yang dilindungi adalah `peristiwa`, `pelaku_id`, `sasaran`, `rincian`, `id_permintaan`, dan
`dicatat_pada` dalam bentuk `Y-m-d H:i:s`. Bentuk kanonik mengurutkan kunci objek menurut abjad di
setiap tingkat dan menolak bilangan pecahan maupun objek.

Tabel `rantai_audit` berisi tepat satu baris kepala rantai yang dibuat oleh migrasi: `hash_terakhir`
dan `jumlah`. Baris pertama menyambung ke 64 angka nol. Menghapus baris terakhir tidak memutus
sambungan apa pun, maka panjang rantai dicatat di kepala dan ikut dibandingkan saat verifikasi.

### Respons galat

| Keadaan | Jawaban |
| --- | --- |
| `rincian` memuat kunci terlarang | `LogicException`, transaksi tidak menulis apa pun |
| Kepala rantai tidak ada | `LogicException`, jalankan migrasi |
| Baris diubah atau dihapus lewat model | `LogicException`, baris tetap seperti semula |

Log audit tidak pernah tampil pada API publik. Pembacaannya disediakan bagi auditor pada rilis 1.0.0.

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


---

## Dana, escrow, dan pencairan (LP-8, LP-12, LP-18)

Dana pembelian yang telah dibayar ditahan di dompet sistem `escrow` selama pesanan berlangsung.
Pengeluaran dana dari `escrow` hanya melalui `App\Services\LayananDana` di dalam transaksi
`GembokCheckout` setelah status pesanan berubah melalui `MesinStatusPesanan`:

- `lepaskan(Pesanan $pesanan): void`: hanya sah untuk pesanan berstatus `selesai`. Memindahkan
  komisi (`komisi(Pesanan)` — persentase atas `subtotal_rupiah` dengan pembulatan setengah ke atas,
  ongkir utuh untuk penjual) ke dompet `pendapatan`, serta `bagianPenjual(Pesanan)` ke dompet
  penjual (`penjual:<id>`, dibuat aman secara konkuren saat pertama kali dibutuhkan).
- `kembalikan(Pesanan $pesanan): void`: hanya sah untuk pesanan berbayar yang berstatus
  `dibatalkan` (`dibayar_pada` tidak kosong). Mengembalikan seluruh `total_rupiah` ke dompet
  `eksternal`.
- Tagihan berstatus `menunggu` dapat dibatalkan pembeli sebelum dibayar melalui
  `LayananPembayaran::tandaiDibatalkan()` menjadi status akhir `dibatalkan`; webhook pembayaran
  yang datang sesudahnya ditolak dengan `409` (`LP-10`).

### Pencairan saldo penjual

Pengajuan pencairan saldo penjual ditahan terlebih dahulu di dompet sistem `penahan`
(`JenisDompet::Penahan`, arah mutasi `tahan_pencairan` -> `pencairan` / `batal_pencairan`)
sebelum diputuskan staf keuangan lain (`pengaju_id !== diputuskan_oleh`, LP-18) dengan status
`menunggu`, `disetujui`, atau `ditolak`.

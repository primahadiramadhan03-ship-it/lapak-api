# Review PR B2 — Endpoint Registrasi (sesi-1/registrasi-prima)

## Ringkasan

Branch ini berusaha mengimplementasikan endpoint registrasi pengguna (D1 — Identitas). Ada 2 masalah kritis yang membuat test gagal.

## Masalah Kritis

### 1. `Controller::sukses()` belum didefinisikan
`RegistrasiController` memanggil `$this->sukses(...)` tapi `app/Http/Controllers/Controller.php` di branch ini masih kosong.

**Dampak:** Semua request mengembalikan HTTP 500.

### 2. `RegistrasiController` tidak hash kata sandi
Controller mengirim `kata_sandi` langsung ke `Pengguna::create()` tanpa hashing.

**Dampak:** Kata sandi disimpan dalam bentuk plain text.

## Test Results (Sebelum Perbaikan)

```bash
./vendor/bin/pest --filter RegistrasiTest
```

- **3 failed**, **3 passed**
- Error: `Call to undefined method App\Http\Controllers\RegistrasiController::sukses()`

## Perbaikan yang Dilakukan

Branch perbaikan: `sesi-1/fix-registrasi-syafiq`

### 1. Menambahkan `sukses()` ke `Controller.php`
```php
protected function sukses(mixed $data, int $kode = 200): JsonResponse
{
    return response()->json([
        'data' => $data,
        'meta' => null,
    ], $kode);
}
```

### 2. Hash kata sandi di `RegistrasiController`
```php
$data['kata_sandi'] = Hash::make($data['kata_sandi']);
```

## Test Results (Setelah Perbaikan)

```bash
./vendor/bin/pest --filter RegistrasiTest
```

- **6 passed** (12 assertions)

## Catatan untuk Prima

- `RegistrasiRequest` sudah bagus (validasi lengkap, normalisasi surel, pesan custom)
- Test yang ada sudah solid (hash, duplikat surel, password lemah, konfirmasi tidak cocok)
- Controller sudah mengabaikan `id` dan `status` yang dikirim klien (hanya `safe()->only([...])`)
- Perbaikan ini hanya menambahkan `sukses()` dan hashing password

## Cara Mengambil Perbaikan

```bash
git merge sesi-1/fix-registrasi-syafiq
```

atau cherry-pick commit `d2de2c6`.

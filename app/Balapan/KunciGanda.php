<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Services\PenerbitSesi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class KunciGanda extends SkenarioBalapan
{
    use PanggilHttp;

    public function nama(): string
    {
        return 'kunci-ganda';
    }

    public function deskripsi(): string
    {
        return 'Enam proses serentak dengan Idempotency-Key sama: tepat 1 mutasi checkout tercipta';
    }

    public function jumlahPembalap(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $pembeli = Pengguna::factory()->create();
        $alamat = Alamat::factory()->for($pembeli)->create();
        $produk = Produk::factory()->create(['stok' => 50]);

        if (Schema::hasTable('keranjang')) {
            $keranjangId = DB::table('keranjang')->insertGetId([
                'pengguna_id' => $pembeli->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('butir_keranjang')->insert([
                'keranjang_id' => $keranjangId,
                'produk_id' => $produk->id,
                'kuantitas' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        [, $token] = app(PenerbitSesi::class)->terbitkan($pembeli, 'Balapan Kunci Ganda');

        return [
            'pembeli' => $pembeli->id,
            'alamat' => $alamat->id,
            'produk' => $produk->id,
            'kunci_sesi' => $token,
            'kunci_idempotensi' => 'KUNCI-GANDA-'.Str::uuid(),
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $badan = ['alamat_id' => $konteks['alamat']];

        $hasil = $this->panggilJson('POST', '/api/v1/checkout', $badan, [
            'Authorization' => 'Bearer '.$konteks['kunci_sesi'],
            'Idempotency-Key' => $konteks['kunci_idempotensi'],
        ]);

        $status = $hasil['status'];
        $json = $hasil['data'];
        $headers = $hasil['headers'];

        if ($status !== 201) {
            return [
                'status' => $this->ringkas($status, $json),
                'sukses' => false,
            ];
        }

        $isReplayed = isset($headers['idempotency-replayed']) || isset($headers['Idempotency-Replayed']);
        $jenis = $isReplayed ? 'ulangan' : 'pertama';
        $kode = (string) ($json['data']['kode'] ?? '');

        return [
            'status' => "201 {$jenis} ".substr($kode, -6),
            'jenis' => $jenis,
            'kode' => substr($kode, -6),
            'sukses' => true,
        ];
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $checkout = Checkout::query()->where('pembeli_id', $konteks['pembeli'])->count();
        $produk = Produk::query()->find($konteks['produk']);
        $stok = $produk?->stok ?? 50;

        $pertama = 0;
        $kode = [];

        foreach ($hasil as $h) {
            $statusText = is_array($h) ? ($h['status'] ?? '') : (string) $h;
            if (str_starts_with($statusText, '201 pertama')) {
                $pertama++;
            }
            if (is_array($h) && ! empty($h['kode'])) {
                $kode[] = $h['kode'];
            }
        }

        $kodeUnik = array_unique($kode);

        $pelanggaran = [];
        if ($checkout !== 1) {
            $pelanggaran[] = "Lahir {$checkout} checkout dari satu Idempotency-Key, seharusnya 1.";
        }
        if ($pertama !== 1) {
            $pelanggaran[] = "{$pertama} proses merasa menjadi yang pertama, seharusnya tepat 1.";
        }
        if (count($kodeUnik) > 1) {
            $pelanggaran[] = 'Proses yang berbeda menerima jawaban yang berbeda.';
        }
        if ($stok !== 48) {
            $pelanggaran[] = 'Stok berkurang '.(50 - $stok).', padahal keranjang hanya berisi 2.';
        }

        $valid = empty($pelanggaran);

        return [
            'sukses' => count(array_filter($hasil, fn ($h) => is_array($h) ? ($h['sukses'] ?? false) : false)),
            'gagal' => count($pelanggaran),
            'valid' => $valid,
            'pesan' => $valid
                ? 'LULUS: 6 proses serentak, tidak ada aturan yang dilanggar.'
                : 'GAGAL: Terdeteksi pelanggaran: '.implode('; ', $pelanggaran),
            'rincian' => [
                'checkout_dibuat' => $checkout,
                'proses_pertama' => $pertama,
                'stok_akhir' => $stok,
                'pelanggaran' => $pelanggaran,
            ],
        ];
    }

    private function ringkas(int $status, array $json): string
    {
        $kode = $json['galat']['kode'] ?? $json['pesan'] ?? 'galat';

        return "{$status} {$kode}";
    }
}

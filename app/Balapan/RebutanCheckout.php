<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\PenerbitSesi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class RebutanCheckout extends SkenarioBalapan
{
    use PanggilHttp;

    private const STOK_AWAL = 5;

    public function nama(): string
    {
        return 'rebutan-checkout';
    }

    public function deskripsi(): string
    {
        return 'Dua belas pembeli serentak berebut stok terbatas lewat seluruh lapisan (LP-4, LP-9, LP-11)';
    }

    public function jumlahPembalap(): int
    {
        return 12;
    }

    public function siapkan(): array
    {
        $produk = Produk::factory()->create(['stok' => self::STOK_AWAL]);

        $pembeliData = [];

        foreach (range(1, $this->jumlahPembalap()) as $i) {
            $pembeli = Pengguna::factory()->pembeli()->create();
            $alamat = Alamat::factory()->for($pembeli)->create();

            if (Schema::hasTable('keranjang')) {
                $keranjangId = DB::table('keranjang')->insertGetId([
                    'pengguna_id' => $pembeli->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('butir_keranjang')->insert([
                    'keranjang_id' => $keranjangId,
                    'produk_id' => $produk->id,
                    'kuantitas' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            [, $token] = app(PenerbitSesi::class)->terbitkan($pembeli, "Pembeli Balapan {$i}");

            $pembeliData[$i] = [
                'pembeli_id' => $pembeli->id,
                'alamat_id' => $alamat->id,
                'kunci_sesi' => $token,
                'kunci_idempotensi' => 'REBUTAN-'.Str::uuid(),
            ];
        }

        return [
            'produk_id' => $produk->id,
            'stok_awal' => self::STOK_AWAL,
            'pembeli' => $pembeliData,
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $dataPembeli = $konteks['pembeli'][$indeks] ?? null;
        if ($dataPembeli === null) {
            return ['status' => 'data pembeli tidak ditemukan', 'sukses' => false];
        }

        $hasil = $this->panggilJson('POST', '/api/v1/checkout', [
            'alamat_id' => $dataPembeli['alamat_id'],
        ], [
            'Authorization' => 'Bearer '.$dataPembeli['kunci_sesi'],
            'Idempotency-Key' => $dataPembeli['kunci_idempotensi'],
        ]);

        $status = $hasil['status'];
        $json = $hasil['data'];

        if ($status === 201) {
            return [
                'status' => '201 dibuat',
                'sukses' => true,
                'kode' => $json['data']['kode'] ?? '',
                'pembalap' => $indeks,
            ];
        }

        $pesanGalat = $json['galat']['kode'] ?? $json['pesan'] ?? 'ditolak';

        return [
            'status' => "{$status} {$pesanGalat}",
            'sukses' => false,
            'pembalap' => $indeks,
        ];
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $produkId = $konteks['produk_id'];
        $stokAwal = $konteks['stok_awal'];

        $produk = Produk::query()->find($produkId);
        $stokSisa = $produk?->stok ?? 0;

        $pembeliIds = array_column($konteks['pembeli'], 'pembeli_id');

        $totalCheckout = Checkout::query()->whereIn('pembeli_id', $pembeliIds)->count();
        $totalPesanan = Pesanan::query()->whereIn('checkout_id', Checkout::query()->whereIn('pembeli_id', $pembeliIds)->pluck('id'))->count();
        $totalTagihan = Pembayaran::query()->whereIn('checkout_id', Checkout::query()->whereIn('pembeli_id', $pembeliIds)->pluck('id'))->count();

        $jumlah201 = 0;
        foreach ($hasil as $h) {
            $statusText = is_array($h) ? ($h['status'] ?? '') : (string) $h;
            if (str_starts_with($statusText, '201')) {
                $jumlah201++;
            }
        }

        $pelanggaran = [];

        if ($jumlah201 > $stokAwal) {
            $pelanggaran[] = "Terjadi overselling: {$jumlah201} checkout berhasil, padahal stok hanya {$stokAwal}.";
        }

        if ($totalCheckout !== $jumlah201) {
            $pelanggaran[] = "Terdapat {$totalCheckout} checkout di basis data, tetapi jawaban 201 ada {$jumlah201}.";
        }

        if ($totalPesanan !== $totalCheckout) {
            $pelanggaran[] = "Terdapat pesanan gantung/inkonsisten: {$totalPesanan} pesanan dari {$totalCheckout} checkout.";
        }

        if ($totalTagihan !== $totalCheckout) {
            $pelanggaran[] = "Terdapat tagihan pembayaran inkonsisten: {$totalTagihan} tagihan dari {$totalCheckout} checkout.";
        }

        $stokHarusnya = $stokAwal - $jumlah201;
        if ($stokSisa !== $stokHarusnya) {
            $pelanggaran[] = "Stok tersisa {$stokSisa}, seharusnya {$stokHarusnya}.";
        }

        $valid = empty($pelanggaran);

        return [
            'sukses' => $jumlah201,
            'gagal' => count($hasil) - $jumlah201,
            'valid' => $valid,
            'pesan' => $valid
                ? "LULUS: {$jumlah201} pembeli berhasil checkout, sisa stok aman ({$stokSisa}), tidak ada data setengah jadi."
                : 'GAGAL: Terdeteksi inkonsistensi: '.implode('; ', $pelanggaran),
            'rincian' => [
                'checkout_sukses' => $jumlah201,
                'stok_awal' => $stokAwal,
                'stok_sisa' => $stokSisa,
                'total_checkout_db' => $totalCheckout,
                'total_pesanan_db' => $totalPesanan,
                'total_tagihan_db' => $totalTagihan,
                'pelanggaran' => $pelanggaran,
            ],
        ];
    }
}

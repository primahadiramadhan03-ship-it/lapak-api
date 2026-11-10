<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Models\Alamat;
use App\Models\Checkout;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;
use App\Services\PenerbitSesi;
use Illuminate\Support\Str;

/**
 * Satu keranjang di-checkout dari 6 tab serentak dengan Idempotency-Key berbeda.
 * Aturan (LP-4, LP-7): tepat satu checkout yang berhasil, stok berkurang 2,
 * dan 5 lainnya ditolak dengan KERANJANG_KOSONG.
 */
final class KeranjangGanda extends SkenarioBalapan
{
    use PanggilHttp;

    public const STOK_AWAL = 5;

    public function nama(): string
    {
        return 'keranjang-ganda';
    }

    public function deskripsi(): string
    {
        return 'Satu keranjang di-checkout dari banyak tab, kunci berbeda: tepat satu checkout';
    }

    public function jumlahPembalap(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $pembeli = Pengguna::factory()->pembeli()->create();
        $alamat = Alamat::factory()->for($pembeli, 'pengguna')->create([
            'nama_penerima' => 'Pembeli Balapan',
            'jalan' => 'Jl. Balapan No. 1',
        ]);

        $penjual = Pengguna::factory()->penjual()->create();
        $toko = Toko::factory()->for($penjual, 'pemilik')->create([
            'nama' => 'Toko Balapan',
            'ongkir_rupiah' => 10_000,
        ]);

        $produk = Produk::factory()->for($toko)->create([
            'nama' => 'Barang Rebutan Keranjang',
            'harga_rupiah' => 50_000,
            'stok' => self::STOK_AWAL,
        ]);

        app(LayananKeranjang::class)->aturButir($pembeli, $produk->id, 2);

        [, $kunci] = app(PenerbitSesi::class)->terbitkan($pembeli, 'Tab Balapan');

        return [
            'pembeli' => $pembeli->id,
            'alamat' => $alamat->id,
            'produk' => $produk->id,
            'kunci' => $kunci,
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $badan = ['alamat_id' => $konteks['alamat']];

        $res = $this->panggilJson('POST', '/api/v1/checkout', $badan, [
            'Authorization' => 'Bearer '.$konteks['kunci'],
            'Idempotency-Key' => "tab-{$indeks}-".Str::random(16),
        ]);

        return [
            'pembalap' => $indeks,
            'status' => $res['status'],
            'kode' => $res['data']['galat']['kode'] ?? ($res['status'] === 201 ? '201' : 'GALAT'),
        ];
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $checkout = Checkout::query()->where('pembeli_id', $konteks['pembeli'])->count();
        $stok = Produk::query()->findOrFail($konteks['produk'])->stok;

        $pelanggaran = [];
        if ($checkout !== 1) {
            $pelanggaran[] = "Lahir {$checkout} checkout dari satu keranjang, seharusnya 1.";
        }
        if ($stok !== self::STOK_AWAL - 2) {
            $terjual = self::STOK_AWAL - $stok;
            $pelanggaran[] = "Stok berkurang {$terjual}, padahal keranjang hanya berisi 2.";
        }

        $c201 = 0;
        $c422 = 0;
        foreach ($hasil as $h) {
            $status = $h['status'] ?? null;
            if ($status === 201) {
                $c201++;
            } elseif ($status === 422) {
                $c422++;
            }
        }

        $valid = empty($pelanggaran);

        return [
            'sukses' => $c201,
            'gagal' => $c422,
            'valid' => $valid,
            'pesan' => $valid
                ? 'LULUS: 6 proses serentak, tidak ada aturan yang dilanggar.'
                : implode(' ', $pelanggaran),
            'rincian' => [
                'checkout_terbuat' => $checkout,
                'sisa_stok' => $stok,
                'respons_201' => $c201,
                'respons_422' => $c422,
            ],
        ];
    }
}

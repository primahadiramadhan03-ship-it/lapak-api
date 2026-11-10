<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Exceptions\ProdukTidakDapatDibeli;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PenjagaStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dua belas pembeli berebut lima barang terakhir. Masing-masing membeli satu.
 * Aturannya (LP-4): yang terbeli ditambah yang tersisa harus sama dengan stok awal.
 */
final class RebutanStok extends SkenarioBalapan
{
    private const STOK_AWAL = 5;

    public function __construct(private readonly PenjagaStok $penjaga) {}

    public function nama(): string
    {
        return 'RebutanStok';
    }

    public function deskripsi(): string
    {
        return 'Dua belas pembeli berebut '.self::STOK_AWAL.' barang: terjual tepat sebanyak itu';
    }

    public function keterangan(): string
    {
        return $this->deskripsi();
    }

    public function proses(): int
    {
        return 12;
    }

    public function jumlahPembalap(): int
    {
        return $this->proses();
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));
        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $produk = Produk::factory()->for($toko)->for(Kategori::query()->firstOrFail())->create([
            'slug' => "produk-{$akhiran}",
            'stok' => self::STOK_AWAL,
        ]);

        return ['produk' => $produk->id];
    }

    public function jalankan(mixed $a, mixed $b): string
    {
        $konteks = is_array($a) ? $a : $b;
        $id = $konteks['produk'];

        try {
            DB::transaction(function () use ($id): void {
                $this->penjaga->kurangi($this->penjaga->ambil([$id])[$id], 1);
            });
        } catch (ProdukTidakDapatDibeli) {
            return 'ditolak: stok habis';
        }

        return 'terbeli';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $terbeli = count(array_filter($hasil, fn (string $h) => $h === 'terbeli'));
        $sisa = Produk::query()->findOrFail($konteks['produk'])->stok;
        $awal = self::STOK_AWAL;

        $pelanggaran = [];
        if ($terbeli > $awal) {
            $pelanggaran[] = "{$terbeli} pembeli berhasil, padahal barangnya hanya {$awal}.";
        }
        if ($terbeli + $sisa !== $awal) {
            $pelanggaran[] = "Terbeli {$terbeli} ditambah sisa {$sisa} bukan stok awal {$awal}.";
        }

        return $pelanggaran;
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $rawHasil = array_map(fn ($h) => is_array($h) ? ($h['status'] ?? $h['data'] ?? '') : (string) $h, $hasil);
        $pelanggaran = $this->periksa($konteks, $rawHasil);
        $terbeli = count(array_filter($rawHasil, fn ($h) => $h === 'terbeli'));
        $ditolak = count(array_filter($rawHasil, fn ($h) => $h === 'ditolak: stok habis'));
        $sisa = Produk::query()->findOrFail($konteks['produk'])->stok;
        $awal = self::STOK_AWAL;
        $valid = $pelanggaran === [];

        return [
            'sukses' => $terbeli,
            'gagal' => $ditolak,
            'valid' => $valid,
            'pesan' => $valid
                ? "Tepat {$terbeli} barang terjual dan {$sisa} sisa stok dari total {$awal}. Aturan LP-4 terpenuhi."
                : implode(' ', $pelanggaran),
            'rincian' => [
                'terbeli' => $terbeli,
                'ditolak' => $ditolak,
                'sisa' => $sisa,
                'pelanggaran' => implode('; ', $pelanggaran),
            ],
        ];
    }
}

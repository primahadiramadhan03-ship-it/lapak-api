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
 * Skenario balapan penyesuaian stok oleh penjual selagi pembeli berbelanja.
 * Tidak boleh ada pembaruan stok yang tertimpa / hilang (LP-4).
 */
final class PenyesuaianStok extends SkenarioBalapan
{
    private const STOK_AWAL = 10;

    private const RESTOK = 3;

    public function __construct(private readonly PenjagaStok $penjaga) {}

    public function nama(): string
    {
        return 'PenyesuaianStok';
    }

    public function deskripsi(): string
    {
        return 'Penjual menambah stok selagi pembeli berbelanja: tidak ada perubahan yang hilang';
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
        $nomor = is_int($a) ? $a : (int) $b;
        $id = $konteks['produk'];

        if ($nomor % 2 === 0) {
            $this->penjaga->sesuaikan($id, self::RESTOK);

            return 'penjual: tambah '.self::RESTOK;
        }

        try {
            DB::transaction(function () use ($id): void {
                $this->penjaga->kurangi($this->penjaga->ambil([$id])[$id], 1);
            });
        } catch (ProdukTidakDapatDibeli) {
            return 'pembeli: ditolak';
        }

        return 'pembeli: beli 1';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $terbeli = count(array_filter($hasil, fn (string $h) => $h === 'pembeli: beli 1'));
        $restok = count(array_filter($hasil, fn (string $h) => str_starts_with($h, 'penjual')));
        $seharusnya = self::STOK_AWAL - $terbeli + $restok * self::RESTOK;
        $nyata = Produk::query()->findOrFail($konteks['produk'])->stok;

        return $nyata === $seharusnya ? [] : ["Stok akhir {$nyata}, seharusnya {$seharusnya}: ada perubahan yang tertimpa."];
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $rawHasil = array_map(fn ($h) => is_array($h) ? ($h['status'] ?? $h['data'] ?? '') : (string) $h, $hasil);
        $pelanggaran = $this->periksa($konteks, $rawHasil);
        $terbeli = count(array_filter($rawHasil, fn ($h) => $h === 'pembeli: beli 1'));
        $restok = count(array_filter($rawHasil, fn ($h) => str_starts_with($h, 'penjual')));
        $nyata = Produk::query()->findOrFail($konteks['produk'])->stok;
        $seharusnya = self::STOK_AWAL - $terbeli + $restok * self::RESTOK;
        $valid = $pelanggaran === [];

        return [
            'sukses' => $terbeli + $restok,
            'gagal' => count($rawHasil) - ($terbeli + $restok),
            'valid' => $valid,
            'pesan' => $valid
                ? "Stok akhir {$nyata} sesuai perhitungan {$seharusnya}. Seluruh transaksi serentak aman."
                : implode(' ', $pelanggaran),
            'rincian' => [
                'pembeli_beli' => $terbeli,
                'penjual_tambah' => $restok,
                'stok_akhir' => $nyata,
                'stok_seharusnya' => $seharusnya,
                'pelanggaran' => implode('; ', $pelanggaran),
            ],
        ];
    }
}

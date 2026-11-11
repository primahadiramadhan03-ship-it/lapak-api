<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Exceptions\ProdukTidakDapatDibeli;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Kategori;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\PenjagaStok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BatalSaatCheckout extends SkenarioBalapan
{
    private const STOK_AWAL = 10;

    public function __construct(
        private readonly PenjagaStok $penjaga = new PenjagaStok,
    ) {}

    public function nama(): string
    {
        return 'batal-saat-checkout';
    }

    public function deskripsi(): string
    {
        return 'Pembatalan otomatis bertemu pembelian produk yang sama: tidak ada kebuntuan';
    }

    public function jumlahPembalap(): int
    {
        return 12;
    }

    public function siapkan(): array
    {
        $akhiran = Str::lower(Str::random(8));
        $toko = Toko::factory()->create(['slug' => "toko-{$akhiran}"]);
        $kategori = Kategori::query()->firstOrFail();

        [$kecil, $besar] = Produk::factory()->count(2)->for($toko)->for($kategori)
            ->sequence(['slug' => "produk-a-{$akhiran}"], ['slug' => "produk-b-{$akhiran}"])
            ->create(['stok' => self::STOK_AWAL])
            ->all();

        $checkout = [];
        foreach (range(1, intdiv($this->jumlahPembalap(), 2)) as $ke) {
            $induk = Checkout::factory()->create();
            $pesanan = Pesanan::factory()->for($induk)->for($toko)->create();
            // Nomor produk besar disimpan lebih dulu.
            ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $besar->id, 'kuantitas' => 1]);
            ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $kecil->id, 'kuantitas' => 1]);
            Pembayaran::factory()->for($induk)->create(['batas_bayar_pada' => now()->subMinute()]);
            $checkout[] = $induk->id;
        }

        return ['produk' => [$kecil->id, $besar->id], 'checkout' => $checkout];
    }

    public function jalankan(int $nomor, array $konteks): mixed
    {
        if ($nomor % 2 === 0) {
            $id = $konteks['checkout'][intdiv($nomor, 2) - 1];

            if (class_exists('App\\Services\\PembatalanOtomatis')) {
                $pembatalan = app('App\\Services\\PembatalanOtomatis');

                return $pembatalan->batalkan($id) ? 'penjadwal: batal' : 'penjadwal: lewati';
            }

            return 'penjadwal: batal';
        }

        try {
            DB::transaction(function () use ($konteks): void {
                foreach ($this->penjaga->ambil($konteks['produk']) as $produk) {
                    $this->penjaga->kurangi($produk, 1);
                }
            });
        } catch (ProdukTidakDapatDibeli) {
            return 'pembeli: ditolak';
        }

        return 'pembeli: beli 2';
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $daftarHasil = array_map(fn ($h) => is_array($h) ? ($h['status'] ?? $h['hasil'] ?? '') : (string) $h, $hasil);

        $batal = count(array_filter($daftarHasil, fn (string $h) => $h === 'penjadwal: batal'));
        $beli = count(array_filter($daftarHasil, fn (string $h) => $h === 'pembeli: beli 2'));
        $seharusnya = self::STOK_AWAL + $batal - $beli;

        $pelanggaran = [];
        if ($batal + $beli !== count($daftarHasil)) {
            $pelanggaran[] = 'Ada proses yang gagal, misalnya karena kebuntuan (deadlock).';
        }

        foreach (Produk::query()->findMany($konteks['produk']) as $produk) {
            if ($produk->stok !== $seharusnya) {
                $pelanggaran[] = "Stok produk {$produk->id} adalah {$produk->stok}, "
                    ."seharusnya {$seharusnya}.";
            }
        }

        $valid = empty($pelanggaran);

        return [
            'sukses' => $batal + $beli,
            'gagal' => count($pelanggaran),
            'valid' => $valid,
            'pesan' => $valid
                ? 'LULUS: 12 proses serentak, tidak ada aturan yang dilanggar.'
                : 'GAGAL: Terdeteksi pelanggaran: '.implode('; ', $pelanggaran),
            'rincian' => [
                'batal' => $batal,
                'beli' => $beli,
                'pelanggaran' => $pelanggaran,
            ],
        ];
    }
}

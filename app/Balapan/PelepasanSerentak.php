<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use Database\Seeders\DompetSeeder;

/**
 * Skenario balapan untuk PR A2 Sesi 5: empat pelepasan dana serentak untuk penjual
 * yang belum memiliki dompet. Dompet penjual harus lahir tepat satu kali, tidak ada
 * deadlock maupun duplikat, dan saldo penjual serta pendapatan bertambah akurat.
 */
final class PelepasanSerentak extends SkenarioBalapan
{
    public const SUBTOTAL = 100_000;

    public const ONGKIR = 10_000;

    public const TOTAL = 110_000;

    public function nama(): string
    {
        return 'pelepasan-serentak';
    }

    public function deskripsi(): string
    {
        return 'Empat pelepasan dana serentak untuk penjual baru: dompet lahir tepat satu kali dan saldo akurat';
    }

    public function jumlahPembalap(): int
    {
        return 4;
    }

    public function siapkan(): array
    {
        app(DompetSeeder::class)->run();

        $buku = app(BukuBesar::class);
        $eksternal = $buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);
        $pendapatan = $buku->dompetSistem(JenisDompet::Pendapatan);

        $penjual = Pengguna::factory()->penjual()->create();
        $toko = Toko::factory()->for($penjual, 'pemilik')->create(['ongkir_rupiah' => self::ONGKIR]);

        $pasangan = [];
        foreach (range(1, $this->jumlahPembalap()) as $i) {
            $checkout = Checkout::factory()->create(['total_rupiah' => self::TOTAL]);

            $pesanan = new Pesanan;
            $pesanan->forceFill([
                'checkout_id' => $checkout->id,
                'pembeli_id' => $checkout->pembeli_id,
                'toko_id' => $toko->id,
                'status' => StatusPesanan::Dikirim,
                'subtotal_rupiah' => self::SUBTOTAL,
                'ongkir_rupiah' => self::ONGKIR,
                'total_rupiah' => self::TOTAL,
                'alamat_kirim' => [
                    'nama_penerima' => 'Budi Santoso',
                    'telepon' => '081200000099',
                    'jalan' => 'Jl. Ahmad Yani No. 1',
                    'kota' => 'Surakarta',
                    'kode_pos' => '57100',
                ],
                'dibayar_pada' => now(),
            ])->save();

            $pesanan->refresh();
            $buku->pindahkan($eksternal, $escrow, self::TOTAL, JenisMutasi::DanaMasuk, $pesanan->nomor, 'Simulasi escrow balapan');

            $pasangan[] = [
                'checkout_id' => $checkout->id,
                'pesanan_id' => $pesanan->id,
            ];
        }

        return [
            'penjual_id' => $penjual->id,
            'escrow_awal' => $escrow->refresh()->saldo_rupiah,
            'pendapatan_awal' => $pendapatan->refresh()->saldo_rupiah,
            'pasangan' => $pasangan,
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $pasang = $konteks['pasangan'][$indeks - 1];
        $pesanan = Pesanan::query()->findOrFail($pasang['pesanan_id']);

        app(LayananPesanan::class)->terima($pesanan, PelakuPesanan::Pembeli);

        return 'dilepas';
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $buku = app(BukuBesar::class);
        $dilepas = count(array_filter($hasil, fn (mixed $h) => $h === 'dilepas'));

        $jumlahDompetPenjual = Dompet::query()->where('kode', 'penjual:'.$konteks['penjual_id'])->count();
        $saldoPenjual = (int) Dompet::query()->where('kode', 'penjual:'.$konteks['penjual_id'])->value('saldo_rupiah');

        $komisiPerPesanan = Uang::dari(self::SUBTOTAL)->persen((int) config('lapak.komisi_persen', 5))->rupiah;
        $harapanKomisi = $komisiPerPesanan * $this->jumlahPembalap();
        $harapanPenjual = (self::TOTAL - $komisiPerPesanan) * $this->jumlahPembalap();
        $harapanEscrow = $konteks['escrow_awal'] - (self::TOTAL * $this->jumlahPembalap());

        $escrowAkhir = $buku->dompetSistem(JenisDompet::Escrow)->saldo_rupiah;
        $pendapatanTambah = $buku->dompetSistem(JenisDompet::Pendapatan)->saldo_rupiah - $konteks['pendapatan_awal'];

        $pelanggaran = [];
        if ($dilepas !== $this->jumlahPembalap()) {
            $pelanggaran[] = "Hanya {$dilepas} dari {$this->jumlahPembalap()} pelepasan yang berhasil.";
        }
        if ($jumlahDompetPenjual !== 1) {
            $pelanggaran[] = "Terdapat {$jumlahDompetPenjual} baris dompet penjual, seharusnya tepat 1.";
        }
        if ($saldoPenjual !== $harapanPenjual) {
            $pelanggaran[] = "Saldo penjual {$saldoPenjual}, seharusnya {$harapanPenjual}.";
        }
        if ($pendapatanTambah !== $harapanKomisi) {
            $pelanggaran[] = "Pendapatan komisi bertambah {$pendapatanTambah}, seharusnya {$harapanKomisi}.";
        }
        if ($escrowAkhir !== $harapanEscrow) {
            $pelanggaran[] = "Saldo escrow akhir {$escrowAkhir}, seharusnya {$harapanEscrow}.";
        }
        if (! $buku->periksaKeseimbangan()) {
            $pelanggaran[] = 'Buku besar tidak seimbang.';
        }

        return [
            'sukses' => $dilepas,
            'gagal' => count($pelanggaran),
            'valid' => $pelanggaran === [],
            'pesan' => $pelanggaran === []
                ? count($hasil).' pelepasan serentak selesai dengan 1 dompet penjual dan buku besar seimbang.'
                : implode(' ', $pelanggaran),
            'rincian' => [
                'dilepas' => $dilepas,
                'dompet_penjual' => $jumlahDompetPenjual,
                'saldo_penjual' => $saldoPenjual,
                'komisi_bertambah' => $pendapatanTambah,
                'pelanggaran' => $pelanggaran ? implode(', ', $pelanggaran) : 'tidak ada',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\LogAudit;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use App\Services\PenyelesaianOtomatis;
use Database\Seeders\DompetSeeder;

final class TerimaSerempak extends SkenarioBalapan
{
    use SiapkanPesanan;

    public function __construct(
        private ?LayananPesanan $layanan = null,
        private ?PenyelesaianOtomatis $penjadwal = null,
    ) {
        $this->layanan ??= app(LayananPesanan::class);
        $this->penjadwal ??= app(PenyelesaianOtomatis::class);
    }

    public function nama(): string
    {
        return 'terima-serempak';
    }

    public function deskripsi(): string
    {
        return 'Pembeli dan penjadwal menyelesaikan pesanan yang sama: dana dilepas satu kali';
    }

    public function jumlahPembalap(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        app(DompetSeeder::class)->run();

        $pembeli = Pengguna::factory()->pembeli()->create();
        $penjual = Pengguna::factory()->penjual()->create();
        $toko = Toko::factory()->for($penjual, 'pemilik')->create(['ongkir_rupiah' => self::ONGKIR]);
        $produk = Produk::factory()->for($toko)->create([
            'harga_rupiah' => self::HARGA,
            'stok' => 10,
        ]);

        $pesanan = $this->siapkanPesanan($pembeli, $toko, $produk, 2);
        $pesanan->forceFill([
            'status' => StatusPesanan::Dikirim,
            'dikirim_pada' => now()->subDays(4),
            'nomor_resi' => 'JNE-TERIMA-SEREMPAK',
        ])->save();

        return [
            'pesanan' => $pesanan->id,
            'pembeli' => $pembeli->id,
            'penjual' => $penjual->id,
            'escrow_awal' => $this->saldoSistem(JenisDompet::Escrow),
            'pendapatan_awal' => $this->saldoSistem(JenisDompet::Pendapatan),
        ];
    }

    public function jalankan(mixed $indeksAtauKonteks, mixed $konteksAtauNomor): string
    {
        $konteks = is_array($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;
        $nomor = is_int($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;

        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan']);

        if ($nomor % 2 === 0) {
            return $this->penjadwal->selesaikan($pesanan) ? 'sistem: selesai' : 'sistem: lewati';
        }

        try {
            $pembeli = Pengguna::query()->findOrFail($konteks['pembeli']);
            $this->layanan->terima($pesanan, PelakuPesanan::Pembeli, $pembeli);
        } catch (TransisiPesananTidakSah) {
            return 'pembeli: 409 LP-10';
        }

        return 'pembeli: selesai';
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pesanan = Pesanan::query()->findOrFail($konteks['pesanan']);
        $penjual = Pengguna::query()->findOrFail($konteks['penjual']);
        $selesai = count(array_filter($hasil, fn (string $h) => str_ends_with($h, ': selesai')));
        $keluar = $konteks['escrow_awal'] - $this->saldoSistem(JenisDompet::Escrow);
        $komisi = $this->saldoSistem(JenisDompet::Pendapatan) - $konteks['pendapatan_awal'];
        $diterima = app(BukuBesar::class)->dompetPenjual($penjual)->saldo_rupiah;
        $mutasi = MutasiSaldo::query()->where('rujukan', $pesanan->nomor)->count();
        $jejak = LogAudit::query()->where('sasaran', "pesanan:{$pesanan->nomor}")
            ->where('peristiwa', 'dana.dilepas')
            ->count();

        $pelanggaran = [];
        if ($selesai !== 1) {
            $pelanggaran[] = "{$selesai} proses merasa menyelesaikan pesanan, seharusnya tepat 1.";
        }
        if ($pesanan->status !== StatusPesanan::Selesai) {
            $pelanggaran[] = "Status pesanan {$pesanan->status->value}, seharusnya selesai.";
        }
        if ($keluar !== 382_000) {
            $pelanggaran[] = "Escrow berkurang {$keluar}, seharusnya 382000.";
        }
        if ($diterima !== 363_500 || $komisi !== 18_500) {
            $pelanggaran[] = "Penjual menerima {$diterima} dan komisi {$komisi}, "
                .'seharusnya 363500 dan 18500.';
        }
        // Dana masuk, komisi, dan lepas dana: tiga pemindahan, enam baris.
        if ($mutasi !== 6) {
            $pelanggaran[] = "Tercatat {$mutasi} baris mutasi untuk pesanan ini, seharusnya 6.";
        }
        if ($jejak !== 1) {
            $pelanggaran[] = "Tercatat {$jejak} jejak audit pelepasan dana, seharusnya 1.";
        }

        return $pelanggaran;
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $daftarHasil = array_map(fn ($h) => is_array($h) ? ($h['status'] ?? $h['hasil'] ?? '') : (string) $h, $hasil);
        $pelanggaran = $this->periksa($konteks, $daftarHasil);
        $sukses = count(array_filter($daftarHasil, fn (string $h) => ! str_contains($h, '409') && ! str_contains($h, 'lewati')));
        $gagal = count($daftarHasil) - $sukses;

        return [
            'sukses' => $sukses,
            'gagal' => $gagal,
            'valid' => $pelanggaran === [],
            'pesan' => $pelanggaran === []
                ? "LULUS: {$this->jumlahPembalap()} proses serentak, tidak ada aturan yang dilanggar."
                : 'GAGAL: Terdeteksi pelanggaran: '.implode('; ', $pelanggaran),
            'rincian' => [
                'pelanggaran' => $pelanggaran,
            ],
        ];
    }
}


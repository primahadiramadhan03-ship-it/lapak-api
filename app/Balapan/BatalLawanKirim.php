<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Exceptions\GalatDomain;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Database\Seeders\DompetSeeder;
use Illuminate\Support\Str;

final class BatalLawanKirim extends SkenarioBalapan
{
    use SiapkanPesanan;

    public const STOK_AWAL = 10;

    public function __construct(
        private ?LayananPesanan $layanan = null,
    ) {
        $this->layanan ??= app(LayananPesanan::class);
    }

    public function nama(): string
    {
        return 'batal-lawan-kirim';
    }

    public function deskripsi(): string
    {
        return 'Pembeli membatalkan tepat saat penjual mengirim: tepat satu yang menang';
    }

    public function jumlahPembalap(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        app(DompetSeeder::class)->run();

        $toko = Toko::factory()->create(['ongkir_rupiah' => self::ONGKIR]);
        $pesananIds = [];

        for ($i = 0; $i < 4; $i++) {
            $pembeli = Pengguna::factory()->pembeli()->create();
            $produk = Produk::factory()->for($toko)->create([
                'harga_rupiah' => self::HARGA,
                'stok' => self::STOK_AWAL,
            ]);

            $pesanan = $this->siapkanPesanan($pembeli, $toko, $produk, 2);
            $pesanan->forceFill([
                'status' => StatusPesanan::Dikemas,
                'dikemas_pada' => now(),
            ])->save();

            $pesananIds[] = $pesanan->id;
        }

        return [
            'pesanan' => $pesananIds,
            'escrow_awal' => $this->saldoSistem(JenisDompet::Escrow),
        ];
    }

    public function jalankan(mixed $indeksAtauKonteks, mixed $konteksAtauNomor): string
    {
        $konteks = is_array($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;
        $nomor = is_int($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;

        $indeksPesanan = intdiv($nomor - 1, 2);
        $idPesanan = $konteks['pesanan'][$indeksPesanan];
        $pesanan = Pesanan::query()->findOrFail($idPesanan);

        if ($nomor % 2 === 1) {
            // Pembeli membatalkan
            try {
                $pembeli = Pengguna::query()->findOrFail($pesanan->pembeli_id);
                $this->layanan->batalkan($pesanan, PelakuPesanan::Pembeli, $pembeli, 'Batal sebelum kirim');

                return 'pembeli: batal';
            } catch (TransisiPesananTidakSah|GalatDomain) {
                return 'pembeli: 409 LP-10';
            }
        }

        // Penjual mengirim
        try {
            $nomorResi = 'JNE-'.Str::padLeft((string) $nomor, 6, '0');
            $this->layanan->kirim($pesanan, $nomorResi);

            return 'penjual: kirim';
        } catch (TransisiPesananTidakSah|GalatDomain) {
            return 'penjual: 409 LP-10';
        }
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $pelanggaran = [];
        $kembali = 0;

        foreach (Pesanan::query()->with('butir')->findMany($konteks['pesanan']) as $pesanan) {
            $stok = Produk::query()->findOrFail($pesanan->butir[0]->produk_id)->stok;
            $refund = MutasiSaldo::query()
                ->where('rujukan', $pesanan->nomor)
                ->where('jenis', JenisMutasi::Pengembalian)
                ->count();

            $sah = match ($pesanan->status) {
                // Batal: stok kembali, uang kembali (sepasang mutasi), dan tidak ada resi.
                StatusPesanan::Dibatalkan => $stok === self::STOK_AWAL + 2
                    && $refund === 2 && $pesanan->nomor_resi === null,
                // Dikirim: stok tetap, uang tetap ditahan, dan resinya tercatat.
                StatusPesanan::Dikirim => $stok === self::STOK_AWAL
                    && $refund === 0 && $pesanan->nomor_resi !== null,
                default => false,
            };

            if (! $sah) {
                $pelanggaran[] = "Pesanan {$pesanan->nomor} berakhir {$pesanan->status->value} "
                    ."dengan stok {$stok}, {$refund} baris pengembalian, "
                    .'resi '.($pesanan->nomor_resi ?? 'kosong').'.';
            }

            $kembali += $pesanan->status === StatusPesanan::Dibatalkan ? 382_000 : 0;
        }

        $menang = count(array_filter($hasil, fn (string $h) => ! str_contains($h, '409')));
        if ($menang !== count($konteks['pesanan'])) {
            $pelanggaran[] = "{$menang} tindakan berhasil untuk "
                .count($konteks['pesanan']).' pesanan, seharusnya satu per pesanan.';
        }

        $keluar = $konteks['escrow_awal'] - $this->saldoSistem(JenisDompet::Escrow);
        if ($keluar !== $kembali) {
            $pelanggaran[] = "Escrow berkurang {$keluar}, padahal yang dikembalikan {$kembali}.";
        }

        return $pelanggaran;
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $daftarHasil = array_map(fn ($h) => is_array($h) ? ($h['status'] ?? $h['hasil'] ?? '') : (string) $h, $hasil);
        $pelanggaran = $this->periksa($konteks, $daftarHasil);
        $sukses = count(array_filter($daftarHasil, fn (string $h) => ! str_contains($h, '409')));
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

<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPencairan;
use App\Exceptions\GalatDomain;
use App\Exceptions\SaldoTidakCukup;
use App\Models\Dompet;
use App\Models\LogAudit;
use App\Models\MutasiSaldo;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananPencairan;
use Database\Seeders\DompetSeeder;

final class PencairanGanda extends SkenarioBalapan
{
    private const NOMINAL = 100_000;

    public function __construct(
        private ?LayananPencairan $layanan = null,
    ) {
        $this->layanan ??= app(LayananPencairan::class);
    }

    public function nama(): string
    {
        return 'pencairan-ganda';
    }

    public function deskripsi(): string
    {
        return 'Enam pengajuan pencairan serentak atas saldo terbatas: tepat satu yang berhasil (LP-16)';
    }

    public function jumlahPembalap(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        app(DompetSeeder::class)->run();

        $buku = app(BukuBesar::class);
        $eksternal = $buku->dompetSistem(JenisDompet::Eksternal);
        $penahan = $buku->dompetSistem(JenisDompet::Penahan);

        $penjual = Pengguna::factory()->penjual()->create();
        Toko::factory()->for($penjual, 'pemilik')->create();

        $dompetPenjual = $buku->dompetPenjualAtauBuat($penjual);
        $buku->pindahkan(
            $eksternal,
            $dompetPenjual,
            self::NOMINAL,
            JenisMutasi::LepasDana,
            'SALDO-AWAL-PENCAIRAN-GANDA',
            'Saldo awal uji balapan pencairan ganda'
        );

        return [
            'penjual_id' => $penjual->id,
            'nominal' => self::NOMINAL,
            'penahan_awal' => $penahan->refresh()->saldo_rupiah,
        ];
    }

    public function jalankan(mixed $indeksAtauKonteks, mixed $konteksAtauNomor): string
    {
        $konteks = is_array($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;
        $nomor = is_int($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;

        $penjual = Pengguna::query()->findOrFail($konteks['penjual_id']);

        try {
            $this->layanan->ajukan(
                $penjual,
                Uang::dari($konteks['nominal']),
                "BCA-123456789{$nomor}"
            );

            return 'penjual: diajukan';
        } catch (SaldoTidakCukup|GalatDomain) {
            return 'penjual: saldo tidak cukup';
        }
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $buku = app(BukuBesar::class);
        $diajukan = count(array_filter($hasil, fn (string $h) => $h === 'penjual: diajukan'));
        $ditolak = count(array_filter($hasil, fn (string $h) => $h === 'penjual: saldo tidak cukup'));

        $saldoPenjual = (int) Dompet::query()
            ->where('kode', 'penjual:'.$konteks['penjual_id'])
            ->value('saldo_rupiah');

        $saldoPenahan = (int) Dompet::query()
            ->where('kode', JenisDompet::Penahan->value)
            ->value('saldo_rupiah');

        $jumlahPencairan = Pencairan::query()
            ->where('pengaju_id', $konteks['penjual_id'])
            ->count();

        $pencairanMenunggu = Pencairan::query()
            ->where('pengaju_id', $konteks['penjual_id'])
            ->where('status', StatusPencairan::Menunggu)
            ->count();

        $mutasiTahan = MutasiSaldo::query()
            ->where('jenis', JenisMutasi::TahanPencairan)
            ->count();

        $jejakAudit = LogAudit::query()
            ->where('peristiwa', 'pencairan.diajukan')
            ->where('pelaku_id', $konteks['penjual_id'])
            ->count();

        $pelanggaran = [];

        if ($diajukan !== 1) {
            $pelanggaran[] = "{$diajukan} pengajuan berhasil diproses, seharusnya tepat 1.";
        }

        if ($ditolak !== ($this->jumlahPembalap() - 1)) {
            $pelanggaran[] = "{$ditolak} pengajuan ditolak karena saldo kurang, seharusnya ".($this->jumlahPembalap() - 1).'.';
        }

        if ($saldoPenjual !== 0) {
            $pelanggaran[] = "Saldo penjual tersisa {$saldoPenjual}, seharusnya 0.";
        }

        $penahanHarapan = $konteks['penahan_awal'] + $konteks['nominal'];
        if ($saldoPenahan !== $penahanHarapan) {
            $pelanggaran[] = "Saldo dompet penahan {$saldoPenahan}, seharusnya {$penahanHarapan}.";
        }

        if ($jumlahPencairan !== 1 || $pencairanMenunggu !== 1) {
            $pelanggaran[] = "Tercatat {$jumlahPencairan} baris pencairan ({$pencairanMenunggu} menunggu), seharusnya tepat 1.";
        }

        if ($mutasiTahan < 2) {
            $pelanggaran[] = "Tercatat {$mutasiTahan} mutasi penahan dana, seharusnya minimal 2.";
        }

        if ($jejakAudit !== 1) {
            $pelanggaran[] = "Tercatat {$jejakAudit} jejak audit pencairan.diajukan, seharusnya 1.";
        }

        if (! $buku->periksaKeseimbangan()) {
            $pelanggaran[] = 'Buku besar tidak seimbang.';
        }

        return $pelanggaran;
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $daftarHasil = array_map(fn ($h) => is_array($h) ? ($h['status'] ?? $h['hasil'] ?? '') : (string) $h, $hasil);
        $pelanggaran = $this->periksa($konteks, $daftarHasil);
        $sukses = count(array_filter($daftarHasil, fn (string $h) => $h === 'penjual: diajukan'));
        $gagal = count($daftarHasil) - $sukses;

        return [
            'sukses' => $sukses,
            'gagal' => $gagal,
            'valid' => $pelanggaran === [],
            'pesan' => $pelanggaran === []
                ? "LULUS: {$this->jumlahPembalap()} proses serentak, tepat 1 pengajuan berhasil dan saldo akurat (LP-16)."
                : 'GAGAL: Terdeteksi pelanggaran: '.implode('; ', $pelanggaran),
            'rincian' => [
                'pelanggaran' => $pelanggaran,
                'hasil_pembalap' => $daftarHasil,
            ],
        ];
    }
}

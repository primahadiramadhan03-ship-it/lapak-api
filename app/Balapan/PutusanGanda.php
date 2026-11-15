<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusPencairan;
use App\Exceptions\GalatDomain;
use App\Models\Dompet;
use App\Models\LogAudit;
use App\Models\MutasiSaldo;
use App\Models\Pencairan;
use App\Models\Pengguna;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananPencairan;
use Database\Seeders\DompetSeeder;

final class PutusanGanda extends SkenarioBalapan
{
    private const NOMINAL = 100_000;

    public function __construct(
        private ?LayananPencairan $layanan = null,
    ) {
        $this->layanan ??= app(LayananPencairan::class);
    }

    public function nama(): string
    {
        return 'putusan-ganda';
    }

    public function deskripsi(): string
    {
        return 'Enam staf keuangan serentak memutuskan pencairan yang sama: tepat satu putusan yang berlaku (LP-16)';
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
        $penahanAwal = $penahan->refresh()->saldo_rupiah;

        $penjual = Pengguna::factory()->penjual()->create();
        Toko::factory()->for($penjual, 'pemilik')->create();

        $dompetPenjual = $buku->dompetPenjualAtauBuat($penjual);
        $buku->pindahkan(
            $eksternal,
            $dompetPenjual,
            self::NOMINAL,
            JenisMutasi::LepasDana,
            'SALDO-AWAL-PUTUSAN-GANDA',
            'Saldo awal uji balapan putusan ganda'
        );

        $pencairan = $this->layanan->ajukan(
            $penjual,
            Uang::dari(self::NOMINAL),
            'BCA-9876543210'
        );

        $stafIds = [];
        foreach (range(1, $this->jumlahPembalap()) as $i) {
            $staf = Pengguna::factory()->keuangan()->create(['nama' => "Staf Keuangan {$i}"]);
            $stafIds[] = $staf->id;
        }

        return [
            'pencairan_id' => $pencairan->id,
            'pencairan_kode' => $pencairan->kode,
            'penjual_id' => $penjual->id,
            'nominal' => self::NOMINAL,
            'penahan_awal' => $penahanAwal,
            'staf_ids' => $stafIds,
        ];
    }

    public function jalankan(mixed $indeksAtauKonteks, mixed $konteksAtauNomor): string
    {
        $konteks = is_array($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;
        $nomor = is_int($indeksAtauKonteks) ? $indeksAtauKonteks : $konteksAtauNomor;

        $stafId = $konteks['staf_ids'][$nomor - 1];
        $staf = Pengguna::query()->findOrFail($stafId);
        $pencairan = Pencairan::query()->findOrFail($konteks['pencairan_id']);

        try {
            if ($nomor % 2 === 1) {
                $this->layanan->setujui($pencairan, $staf);

                return 'staf: disetujui';
            }

            $this->layanan->tolak($pencairan, $staf, 'Ditolak dalam balapan');

            return 'staf: ditolak';
        } catch (GalatDomain $e) {
            if ($e->statusHttp === 409) {
                return 'staf: 409 sudah diputuskan';
            }

            return 'staf: galat '.$e->statusHttp;
        }
    }

    public function periksa(array $konteks, array $hasil): array
    {
        $buku = app(BukuBesar::class);
        $pencairan = Pencairan::query()->findOrFail($konteks['pencairan_id']);
        $disetujui = count(array_filter($hasil, fn (string $h) => $h === 'staf: disetujui'));
        $ditolak = count(array_filter($hasil, fn (string $h) => $h === 'staf: ditolak'));
        $konflik = count(array_filter($hasil, fn (string $h) => $h === 'staf: 409 sudah diputuskan'));
        $pemenang = $disetujui + $ditolak;

        $saldoPenahan = (int) Dompet::query()
            ->where('kode', JenisDompet::Penahan->value)
            ->value('saldo_rupiah');

        $saldoPenjual = (int) Dompet::query()
            ->where('kode', 'penjual:'.$konteks['penjual_id'])
            ->value('saldo_rupiah');

        $pelanggaran = [];

        if ($pemenang !== 1) {
            $pelanggaran[] = "{$pemenang} putusan berhasil dieksekusi, seharusnya tepat 1.";
        }

        if ($konflik !== ($this->jumlahPembalap() - 1)) {
            $pelanggaran[] = "{$konflik} staf menerima 409 konflik, seharusnya ".($this->jumlahPembalap() - 1).'.';
        }

        if (! $pencairan->status->akhir()) {
            $pelanggaran[] = "Status pencairan {$pencairan->status->value}, seharusnya sudah berstatus akhir.";
        }

        if ($pencairan->diputuskan_oleh === null) {
            $pelanggaran[] = 'Kolom diputuskan_oleh masih null.';
        } elseif (! in_array($pencairan->diputuskan_oleh, $konteks['staf_ids'], true)) {
            $pelanggaran[] = "Pemutus {$pencairan->diputuskan_oleh} tidak termasuk dalam daftar staf penguji.";
        }

        if ($pencairan->diputuskan_pada === null) {
            $pelanggaran[] = 'Kolom diputuskan_pada masih null.';
        }

        if ($saldoPenahan !== $konteks['penahan_awal']) {
            $pelanggaran[] = "Saldo dompet penahan {$saldoPenahan}, seharusnya {$konteks['penahan_awal']}.";
        }

        if ($pencairan->status === StatusPencairan::Disetujui) {
            if ($saldoPenjual !== 0) {
                $pelanggaran[] = "Pencairan disetujui tetapi saldo penjual {$saldoPenjual}, seharusnya 0.";
            }
            $mutasiPencairan = MutasiSaldo::query()
                ->where('rujukan', $pencairan->kode)
                ->where('jenis', JenisMutasi::Pencairan)
                ->count();
            if ($mutasiPencairan !== 2) {
                $pelanggaran[] = "Tercatat {$mutasiPencairan} mutasi pencairan, seharusnya 2.";
            }
        } elseif ($pencairan->status === StatusPencairan::Ditolak) {
            if ($saldoPenjual !== $konteks['nominal']) {
                $pelanggaran[] = "Pencairan ditolak tetapi saldo penjual {$saldoPenjual}, seharusnya {$konteks['nominal']}.";
            }
            $mutasiBatal = MutasiSaldo::query()
                ->where('rujukan', $pencairan->kode)
                ->where('jenis', JenisMutasi::BatalPencairan)
                ->count();
            if ($mutasiBatal !== 2) {
                $pelanggaran[] = "Tercatat {$mutasiBatal} mutasi pembatalan pencairan, seharusnya 2.";
            }
        }

        $jejakPutusan = LogAudit::query()
            ->where('sasaran', "pencairan:{$pencairan->kode}")
            ->whereIn('peristiwa', ['pencairan.disetujui', 'pencairan.ditolak'])
            ->count();
        if ($jejakPutusan !== 1) {
            $pelanggaran[] = "Tercatat {$jejakPutusan} jejak audit putusan, seharusnya tepat 1.";
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
        $sukses = count(array_filter($daftarHasil, fn (string $h) => in_array($h, ['staf: disetujui', 'staf: ditolak'], true)));
        $gagal = count($daftarHasil) - $sukses;

        return [
            'sukses' => $sukses,
            'gagal' => $gagal,
            'valid' => $pelanggaran === [],
            'pesan' => $pelanggaran === []
                ? "LULUS: {$this->jumlahPembalap()} proses serentak, tepat satu putusan yang berlaku dan saldo akurat (LP-16)."
                : 'GAGAL: Terdeteksi pelanggaran: '.implode('; ', $pelanggaran),
            'rincian' => [
                'pelanggaran' => $pelanggaran,
                'hasil_pembalap' => $daftarHasil,
            ],
        ];
    }
}

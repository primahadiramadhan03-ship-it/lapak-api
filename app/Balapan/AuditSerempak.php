<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Audit\PemeriksaRantaiAudit;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;

/**
 * Skenario balapan penulisan log audit (LP-19).
 *
 * Beberapa proses mencatat peristiwa pada saat yang hampir sama. Setiap penulisan harus
 * menyambung ke hash baris sebelumnya, sehingga satu-satunya cara yang benar adalah mengunci
 * kepala rantai sebelum membaca dan menyambungnya. Tanpa kunci, dua proses membaca kepala yang
 * sama, menulis dua baris dengan `hash_sebelumnya` identik, dan salah satunya pasti gagal di
 * batas unik atau meninggalkan rantai bercabang.
 */
final class AuditSerempak extends SkenarioBalapan
{
    public function nama(): string
    {
        return 'audit-serempak';
    }

    public function deskripsi(): string
    {
        return 'Enam proses mencatat audit serentak: rantai tetap satu jalur dan kepala bertambah tepat enam (LP-19)';
    }

    public function jumlahPembalap(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $pelaku = Pengguna::factory()->admin()->create();
        $kepala = DB::table('rantai_audit')->where('id', 1)->first();

        return [
            'pelaku_id' => $pelaku->id,
            'sasaran' => 'uji:audit-serempak',
            'jumlah_awal' => (int) ($kepala->jumlah ?? 0),
            'kepala_awal' => (string) ($kepala->hash_terakhir ?? ''),
            // Skrip balapan boleh dijalankan berulang pada basis data yang sama; yang dihitung
            // adalah pertambahan dari jalannya kali ini, bukan jumlah mutlak.
            'jejak_awal' => LogAudit::query()->where('sasaran', 'uji:audit-serempak')->count(),
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $pelaku = Pengguna::query()->findOrFail($konteks['pelaku_id']);

        app(PencatatAudit::class)->catat(
            PeristiwaAudit::PembayaranLunas,
            $pelaku,
            $konteks['sasaran'],
            ['indeks' => $indeks],
        );

        return "dicatat: {$indeks}";
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $daftarHasil = array_map(
            fn ($h) => is_array($h) ? ($h['status'] ?? $h['hasil'] ?? '') : (string) $h,
            $hasil,
        );

        $sukses = count(array_filter($daftarHasil, fn (string $h) => str_starts_with($h, 'dicatat:')));
        $gagal = count($daftarHasil) - $sukses;

        $laporan = app(PemeriksaRantaiAudit::class)->periksa();
        $kepala = DB::table('rantai_audit')->where('id', 1)->first();

        $jumlahHarapan = $konteks['jumlah_awal'] + $this->jumlahPembalap();
        $jejakSasaran = LogAudit::query()->where('sasaran', $konteks['sasaran'])->count();
        $jejakHarapan = $konteks['jejak_awal'] + $this->jumlahPembalap();
        $awalanGanda = DB::table('log_audit')
            ->select('hash_sebelumnya')
            ->groupBy('hash_sebelumnya')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $pelanggaran = [];

        if ($sukses !== $this->jumlahPembalap()) {
            $pelanggaran[] = "{$sukses} penulisan berhasil, seharusnya {$this->jumlahPembalap()}.";
        }

        if (! $laporan['utuh']) {
            $pelanggaran[] = 'Rantai audit rusak: '.implode(' ', $laporan['pelanggaran']);
        }

        if ($laporan['jumlah'] !== $jumlahHarapan) {
            $pelanggaran[] = "Rantai berisi {$laporan['jumlah']} baris, seharusnya {$jumlahHarapan}.";
        }

        if ((int) ($kepala->jumlah ?? -1) !== $jumlahHarapan) {
            $pelanggaran[] = 'Kepala rantai menyebut '.($kepala->jumlah ?? '?')." baris, seharusnya {$jumlahHarapan}.";
        }

        if ($jejakSasaran !== $jejakHarapan) {
            $pelanggaran[] = "Tercatat {$jejakSasaran} baris untuk sasaran uji, seharusnya {$jejakHarapan}.";
        }

        if ($awalanGanda > 0) {
            $pelanggaran[] = "Ada {$awalanGanda} hash_sebelumnya yang dipakai lebih dari satu baris (rantai bercabang).";
        }

        return [
            'sukses' => $sukses,
            'gagal' => $gagal,
            'valid' => $pelanggaran === [],
            'pesan' => $pelanggaran === []
                ? "LULUS: {$this->jumlahPembalap()} proses mencatat serentak, rantai tetap satu jalur dan kepala bertambah tepat {$this->jumlahPembalap()} (LP-19)."
                : 'GAGAL: Terdeteksi pelanggaran: '.implode('; ', $pelanggaran),
            'rincian' => [
                'pelanggaran' => $pelanggaran,
                'hasil_pembalap' => $daftarHasil,
                'jumlah_log' => $laporan['jumlah'],
                'kepala_awal' => $konteks['kepala_awal'],
                'kepala_akhir' => $laporan['kepala'],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Models\KunciIdempotensi;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Skenario pengujian idempotensi: beberapa proses serentak mengirimkan
 * kunci idempotensi yang sama untuk satu transaksi (LP-11).
 */
final class IdempotensiGanda extends SkenarioBalapan
{
    public function nama(): string
    {
        return 'IdempotensiGanda';
    }

    public function deskripsi(): string
    {
        return 'Menguji beberapa proses mengirimkan kunci idempotensi identik secara serentak (LP-11)';
    }

    public function jumlahPembalap(): int
    {
        return 5;
    }

    public function siapkan(): array
    {
        $pengguna = Pengguna::first() ?? Pengguna::factory()->create();
        $kunci = 'BALAPAN-IDEM-'.Str::random(10);

        return [
            'pengguna_id' => $pengguna->id,
            'kunci' => $kunci,
            'jalur' => '/api/v1/uji-balapan',
            'metode' => 'POST',
            'payload' => ['nominal' => 100_000],
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $penggunaId = $konteks['pengguna_id'];
        $kunci = $konteks['kunci'];
        $hash = KunciIdempotensi::hitungHash($konteks['payload']);

        try {
            return DB::transaction(function () use ($indeks, $penggunaId, $kunci, $hash, $konteks) {
                $catatan = KunciIdempotensi::query()
                    ->where('pengguna_id', $penggunaId)
                    ->where('kunci', $kunci)
                    ->first();

                if ($catatan !== null && $catatan->status_kode !== null) {
                    return [
                        'pembalap' => $indeks,
                        'status' => 'replayed',
                        'kode' => $catatan->status_kode,
                    ];
                }

                $rekaman = KunciIdempotensi::create([
                    'pengguna_id' => $penggunaId,
                    'kunci' => $kunci,
                    'metode' => $konteks['metode'],
                    'jalur' => $konteks['jalur'],
                    'hash_permintaan' => $hash,
                    'status_kode' => null,
                    'respons' => null,
                ]);

                usleep(15_000);

                $rekaman->forceFill([
                    'status_kode' => 201,
                    'respons' => ['sukses' => true, 'oleh' => $indeks],
                ])->save();

                return [
                    'pembalap' => $indeks,
                    'status' => 'created',
                    'kode' => 201,
                ];
            });
        } catch (\Throwable $e) {
            return [
                'pembalap' => $indeks,
                'status' => 'conflict_or_blocked',
                'pesan' => $e->getMessage(),
            ];
        }
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $created = 0;
        $replayed = 0;
        $conflicts = 0;

        foreach ($hasil as $h) {
            $status = $h['status'] ?? 'unknown';
            if ($status === 'created') {
                $created++;
            } elseif ($status === 'replayed') {
                $replayed++;
            } else {
                $conflicts++;
            }
        }

        $totalBaris = KunciIdempotensi::query()
            ->where('pengguna_id', $konteks['pengguna_id'])
            ->where('kunci', $konteks['kunci'])
            ->count();

        $valid = $created === 1 && $totalBaris === 1;

        return [
            'sukses' => $created + $replayed,
            'gagal' => $conflicts,
            'valid' => $valid,
            'pesan' => $valid
                ? 'Tepat 1 mutasi tercipta di basis data. Efek ganda berhasil dicegah!'
                : "Terjadi anomali: terdapat {$totalBaris} baris kunci dan {$created} mutasi berhasil dibuat.",
            'rincian' => [
                'pembuat_pertama' => $created,
                'replay_tersimpan' => $replayed,
                'konflik_dicegah' => $conflicts,
                'total_baris_db' => $totalBaris,
            ],
        ];
    }
}

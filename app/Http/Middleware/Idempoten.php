<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\KonflikIdempotensi;
use App\Exceptions\KunciIdempotensiDibutuhkan;
use App\Models\KunciIdempotensi;
use App\Models\Pengguna;
use App\Models\Sesi;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class Idempoten
{
    /**
     * Menjaga idempoten pada mutasi krusial (LP-11).
     *
     * @param  Closure(Request): Response  $next
     * @param  string  $mode  'opsional' (default) atau 'wajib'
     */
    public function handle(Request $request, Closure $next, string $mode = 'opsional'): Response
    {
        $kunci = $request->header('Idempotency-Key');

        if ($kunci === null || trim($kunci) === '') {
            if ($mode === 'wajib') {
                throw new KunciIdempotensiDibutuhkan;
            }

            return $next($request);
        }

        $kunci = trim($kunci);
        $pengguna = $this->temukanPengguna($request);
        $penggunaId = $pengguna?->id;
        $metode = $request->method();
        $jalur = $request->path();
        $hashPermintaan = KunciIdempotensi::hitungHash($request->all());

        // 1. Cek apakah kunci sudah pernah sukses dan tersimpan
        $catatan = KunciIdempotensi::query()
            ->where('pengguna_id', $penggunaId)
            ->where('kunci', $kunci)
            ->first();

        if ($catatan !== null && $catatan->status_kode !== null) {
            // Validasi apakah parameter sama persis
            if ($catatan->metode !== $metode || $catatan->jalur !== $jalur || $catatan->hash_permintaan !== $hashPermintaan) {
                throw new KonflikIdempotensi;
            }

            return response()->json($catatan->respons, $catatan->status_kode, [
                'Idempotency-Replayed' => 'true',
            ]);
        }

        // 2. Jalankan dalam transaksi tunggal bersama efeknya
        return DB::transaction(function () use ($next, $request, $penggunaId, $kunci, $metode, $jalur, $hashPermintaan) {
            try {
                // Catat kunci sebelum anak tangga pertama dimulai
                $rekaman = KunciIdempotensi::create([
                    'pengguna_id' => $penggunaId,
                    'kunci' => $kunci,
                    'metode' => $metode,
                    'jalur' => $jalur,
                    'hash_permintaan' => $hashPermintaan,
                    'status_kode' => null,
                    'respons' => null,
                ]);
            } catch (UniqueConstraintViolationException|QueryException $e) {
                // Terjadi balapan serentak dengan kunci yang sama
                $catatanAda = KunciIdempotensi::query()
                    ->where('pengguna_id', $penggunaId)
                    ->where('kunci', $kunci)
                    ->lockForUpdate()
                    ->first();

                if ($catatanAda !== null && $catatanAda->status_kode !== null) {
                    if ($catatanAda->metode !== $metode || $catatanAda->jalur !== $jalur || $catatanAda->hash_permintaan !== $hashPermintaan) {
                        throw new KonflikIdempotensi;
                    }

                    return response()->json($catatanAda->respons, $catatanAda->status_kode, [
                        'Idempotency-Replayed' => 'true',
                    ]);
                }

                throw $e;
            }

            $response = $next($request);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $konten = $response->getContent();
                $isi = json_decode($konten ?: '', true) ?? ['data' => $konten];

                $rekaman->forceFill([
                    'status_kode' => $statusCode,
                    'respons' => $isi,
                ])->save();
            } else {
                // Jika permintaan downstream tidak sukses, hapus rekaman kunci agar tidak meninggalkan kunci tanpa efek (LP-11)
                $rekaman->delete();
            }

            return $response;
        });
    }

    private function temukanPengguna(Request $request): ?Pengguna
    {
        $sesi = $request->attributes->get('sesi');
        if ($sesi instanceof Sesi) {
            return $sesi->pengguna;
        }

        return $request->user();
    }
}

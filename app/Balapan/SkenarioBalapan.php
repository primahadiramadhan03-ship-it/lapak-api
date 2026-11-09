<?php

declare(strict_types=1);

namespace App\Balapan;

abstract class SkenarioBalapan
{
    /**
     * Nama pengenal skenario (misalnya: AlamatPertama, RebutanStok).
     */
    abstract public function nama(): string;

    /**
     * Deskripsi persoalan balapan / perebutan data yang diuji.
     */
    abstract public function deskripsi(): string;

    /**
     * Jumlah proses serentak (pembalap) yang akan diadu.
     */
    public function jumlahPembalap(): int
    {
        return 5;
    }

    /**
     * Menyiapkan baris awal di basis data sebelum balapan dimulai.
     *
     * @return array<string, mixed> Data konteks yang diteruskan ke setiap pembalap
     */
    abstract public function siapkan(): array;

    /**
     * Aksi yang dijalankan oleh masing-masing pembalap secara bersamaan.
     *
     * @param  int  $indeks  Urutan pembalap (1..N)
     * @param  array<string, mixed>  $konteks
     * @return mixed Hasil aksi pembalap (akan diserialisasi ke JSON)
     */
    abstract public function jalankan(int $indeks, array $konteks): mixed;

    /**
     * Memeriksa kondisi akhir basis data dan menyimpulkan integritas.
     *
     * @param  array<int, mixed>  $hasil  Kumpulan keluaran seluruh pembalap
     * @param  array<string, mixed>  $konteks
     * @return array{sukses: int, gagal: int, valid: bool, pesan: string, rincian: array<string, mixed>}
     */
    abstract public function evaluasi(array $hasil, array $konteks): array;
}

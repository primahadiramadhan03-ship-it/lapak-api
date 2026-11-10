<?php
declare(strict_types=1);

namespace App\Balapan;

use App\Models\Alamat;
use App\Models\Pengguna;
use App\Services\PenerbitSesi;
use Illuminate\Support\Facades\DB;

final class AlamatPertama extends SkenarioBalapan
{
    use PanggilHttp;

    public function nama(): string
    {
        return 'alamat-pertama';
    }

    public function deskripsi(): string
    {
        return 'Menguji 8 proses penambahan alamat pertama secara bersamaan dengan batas maksimal 5 alamat.';
    }

    public function jumlahPembalap(): int
    {
        return 8;
    }

    public function siapkan(): array
    {
        $pengguna = Pengguna::factory()->pembeli()->create();

        [, $token] = app(PenerbitSesi::class)->terbitkan(
            $pengguna,
            'Uji balapan alamat'
        );

        return [
            'pengguna_id' => $pengguna->id,
            'token' => $token,
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $respons = $this->panggilJson(
            'POST',
            '/api/v1/alamat',
            [
                'label' => 'Alamat '.$indeks,
                'nama_penerima' => 'Penerima '.$indeks,
                'telepon' => '08123456789',
                'jalan' => 'Jalan Pengujian Nomor '.$indeks,
                'kota' => 'Surakarta',
                'kode_pos' => '57111',
            ],
            [
                'Authorization' => 'Bearer '.$konteks['token'],
            ]
        );

        return [
            'pembalap' => $indeks,
            'status_kode' => $respons['status'],
            'berhasil' => $respons['status'] === 201,
        ];
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $penggunaId = $konteks['pengguna_id'];

        $totalAlamat = Alamat::query()
            ->where('pengguna_id', $penggunaId)
            ->count();

        $totalUtama = Alamat::query()
            ->where('pengguna_id', $penggunaId)
            ->where('utama', true)
            ->count();

        $berhasil = collect($hasil)
            ->where('status_kode', 201)
            ->count();

        $ditolak = collect($hasil)
            ->where('status_kode', 422)
            ->count();

        $valid = $berhasil === 5
            && $ditolak === 3
            && $totalAlamat === 5
            && $totalUtama === 1;

        Pengguna::query()->whereKey($penggunaId)->delete();

        return [
            'sukses' => $berhasil,
            'gagal' => $ditolak,
            'valid' => $valid,
            'pesan' => $valid
                ? 'Integritas alamat terjaga: tepat 5 alamat tersimpan dan hanya 1 menjadi alamat utama.'
                : 'Anomali terdeteksi: hasil tidak memenuhi batas 5 alamat dan tepat 1 alamat utama.',
            'rincian' => [
                'proses_dijalankan' => count($hasil),
                'alamat_berhasil' => $berhasil,
                'alamat_ditolak' => $ditolak,
                'total_alamat_database' => $totalAlamat,
                'total_alamat_utama' => $totalUtama,
            ],
        ];
    }
}

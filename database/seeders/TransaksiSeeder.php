<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Database\Seeder;

final class TransaksiSeeder extends Seeder
{
    public function run(): void
    {
        $buku = app(BukuBesar::class);

        // Buat 2 pengguna contoh
        $sri = Pengguna::factory()->create(['nama' => 'Sri Wahyuni']);
        $wulan = Pengguna::factory()->create(['nama' => 'Wulan Dari']);

        // Dapatkan dompet sistem
        $luar = $buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);

        // Pastikan dompet penjual ada
        $dompetSri = $buku->cariDompetPenjual($sri);
        if ($dompetSri === null) {
            $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
        }

        $dompetWulan = $buku->cariDompetPenjual($wulan);
        if ($dompetWulan === null) {
            $dompetWulan = Dompet::buat('penjual:'.$wulan->id, JenisDompet::Penjual, 0);
        }

        // Transaksi 1: Sri menerima dana
        $buku->pindahkan($luar, $escrow, 500000, JenisMutasi::DanaMasuk, 'TRX-001', 'Deposit Sri');
        $buku->pindahkan($escrow, $dompetSri, 500000, JenisMutasi::LepasDana, 'TRX-001', 'Lepas ke Sri');

        // Transaksi 2: Wulan menerima dana
        $buku->pindahkan($luar, $escrow, 250000, JenisMutasi::DanaMasuk, 'TRX-002', 'Deposit Wulan');
        $buku->pindahkan($escrow, $dompetWulan, 250000, JenisMutasi::LepasDana, 'TRX-002', 'Lepas ke Wulan');
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Enums\StatusAkun;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Services\BukuBesar;
use Illuminate\Database\Seeder;

final class TransaksiSeeder extends Seeder
{
    private const RUJUKAN_CONTOH = 'LPK-CONTOH-0001';

    public function run(): void
    {
        // Idempotensi: Jangan jalankan jika sudah ada mutasi dengan rujukan ini
        if (MutasiSaldo::where('rujukan', self::RUJUKAN_CONTOH)->exists()) {
            $this->command->info('Transaksi contoh sudah ada. Seeder dilewati (idempoten).');

            return;
        }

        $buku = app(BukuBesar::class);

        // Buat atau cari Sri (karena PenggunaSeeder tidak membuatnya)
        $sri = Pengguna::where('surel', 'sri@lapak.test')->first();
        if ($sri === null) {
            $sri = Pengguna::factory()->create([
                'nama' => 'Sri Wahyuni',
                'surel' => 'sri@lapak.test',
                'status' => StatusAkun::Aktif,
            ]);
            $this->command->info('Pengguna Sri dibuat.');
        }

        // Dapatkan atau buat dompet sistem
        $luar = $buku->dompetSistem(JenisDompet::Eksternal);
        $escrow = $buku->dompetSistem(JenisDompet::Escrow);
        $pendapatan = $buku->dompetSistem(JenisDompet::Pendapatan);

        // Pastikan dompet penjual Sri ada
        $dompetSri = $buku->cariDompetPenjual($sri);
        if ($dompetSri === null) {
            $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
        }

        // Skenario sesuai modul B5:
        // 1. Dana masuk Rp382.000 (Eksternal → Escrow) → 2 mutasi
        $buku->pindahkan($luar, $escrow, 382000, JenisMutasi::DanaMasuk, self::RUJUKAN_CONTOH, 'Dana masuk dari pembeli');

        // 2. Komisi Rp18.500 (Escrow → Pendapatan) → 2 mutasi
        $buku->pindahkan($escrow, $pendapatan, 18500, JenisMutasi::Komisi, self::RUJUKAN_CONTOH, 'Komisi platform');

        // 3. Pelepasan dana Rp363.500 (Escrow → Penjual) → 2 mutasi
        //    382.000 - 18.500 = 363.500 ✓
        $buku->pindahkan($escrow, $dompetSri, 363500, JenisMutasi::LepasDana, self::RUJUKAN_CONTOH, 'Dana dilepas ke penjual');

        $this->command->info('Transaksi contoh berhasil dibuat (6 mutasi dengan rujukan '.self::RUJUKAN_CONTOH.').');
    }
}

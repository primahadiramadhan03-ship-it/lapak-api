<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
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
        // Idempotensi: Jangan jalankan jika sudah ada transaksi dengan rujukan ini
        if (MutasiSaldo::where('rujukan', self::RUJUKAN_CONTOH)->exists()) {
            $this->command->info('Transaksi contoh sudah ada. Seeder dilewati (idempoten).');

            return;
        }

        $buku = app(BukuBesar::class);

        // Buat atau cari pengguna contoh
        $sri = Pengguna::firstOrCreate(
            ['surel' => 'sri@example.com'],
            ['nama' => 'Sri Wahyuni', 'telepon' => '081234567890', 'kata_sandi' => bcrypt('password')]
        );

        $wulan = Pengguna::firstOrCreate(
            ['surel' => 'wulan@example.com'],
            ['nama' => 'Wulan Dari', 'telepon' => '081234567891', 'kata_sandi' => bcrypt('password')]
        );

        // Dapatkan atau buat dompet sistem
        $luar = Dompet::query()->where('jenis', JenisDompet::Eksternal)->first();
        if ($luar === null) {
            $luar = Dompet::buat('eksternal', JenisDompet::Eksternal, 10000000); // Saldo awal besar
        }

        $escrow = Dompet::query()->where('jenis', JenisDompet::Escrow)->first();
        if ($escrow === null) {
            $escrow = Dompet::buat('escrow', JenisDompet::Escrow, 0);
        }

        // Pastikan dompet penjual ada
        $dompetSri = $buku->cariDompetPenjual($sri);
        if ($dompetSri === null) {
            $dompetSri = Dompet::buat('penjual:'.$sri->id, JenisDompet::Penjual, 0);
        }

        $dompetWulan = $buku->cariDompetPenjual($wulan);
        if ($dompetWulan === null) {
            $dompetWulan = Dompet::buat('penjual:'.$wulan->id, JenisDompet::Penjual, 0);
        }

        // Skenario contoh: Sri menerima dana
        $buku->pindahkan($luar, $escrow, 500000, JenisMutasi::DanaMasuk, self::RUJUKAN_CONTOH, 'Deposit Sri');
        $buku->pindahkan($escrow, $dompetSri, 500000, JenisMutasi::LepasDana, self::RUJUKAN_CONTOH, 'Lepas ke Sri');

        // Skenario contoh: Wulan menerima dana
        $buku->pindahkan($luar, $escrow, 250000, JenisMutasi::DanaMasuk, self::RUJUKAN_CONTOH.'-2', 'Deposit Wulan');
        $buku->pindahkan($escrow, $dompetWulan, 250000, JenisMutasi::LepasDana, self::RUJUKAN_CONTOH.'-2', 'Lepas ke Wulan');

        $this->command->info('Transaksi contoh berhasil dibuat.');
    }
}

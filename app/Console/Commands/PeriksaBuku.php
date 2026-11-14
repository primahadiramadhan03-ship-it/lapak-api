<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\StatusPencairan;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pencairan;
use App\Models\Pesanan;
use App\Services\BukuBesar;
use Illuminate\Console\Command;

final class PeriksaBuku extends Command
{
    protected $signature = 'lapak:periksa-buku';

    protected $description = 'Memeriksa enam invarian buku besar; kode keluar 1 bila dilanggar.';

    public function handle(BukuBesar $bukuBesar): int
    {
        $pelanggaran = [];

        // 1. Keseimbangan buku besar (LP-13)
        $totalMutasi = (int) MutasiSaldo::sum('jumlah_rupiah');
        if ($totalMutasi !== 0) {
            $pelanggaran[] = "Total seluruh mutasi {$totalMutasi}, seharusnya 0.";
        }

        // 2. Setiap kelompok mutasi bernilai 0
        $kelompokTidakNol = MutasiSaldo::query()
            ->selectRaw('kelompok, SUM(jumlah_rupiah) as total')
            ->groupBy('kelompok')
            ->havingRaw('SUM(jumlah_rupiah) != 0')
            ->count();

        if ($kelompokTidakNol > 0) {
            $pelanggaran[] = "Ada {$kelompokTidakNol} kelompok mutasi yang tidak berjumlah nol.";
        }

        // 3. Saldo dompet sama dengan riwayat mutasinya
        $dompetList = Dompet::all();
        $baris = [];

        foreach ($dompetList as $dompet) {
            $totalMutasiDompet = (int) MutasiSaldo::where('dompet_id', $dompet->id)->sum('jumlah_rupiah');
            if ($dompet->saldo_rupiah !== $totalMutasiDompet) {
                $pelanggaran[] = "Saldo dompet [{$dompet->kode}] adalah {$dompet->saldo_rupiah}, tetapi riwayat mutasinya {$totalMutasiDompet}.";
            }

            // 4. Dompet non-eksternal tidak boleh negatif
            if (! $dompet->jenis->bolehNegatif() && $dompet->saldo_rupiah < 0) {
                $pelanggaran[] = "Dompet non-eksternal [{$dompet->kode}] bersaldo negatif: {$dompet->saldo_rupiah}.";
            }

            $baris[] = [$dompet->kode, $dompet->jenis->value, number_format($dompet->saldo_rupiah, 0, ',', '.')];
        }

        // 5. Dana yang ditahan sama dengan nilai pesanan yang sudah dibayar dan belum selesai.
        //    Invarian ini mengikat buku besar ke tabel pesanan: uang di escrow selalu dapat
        //    ditunjuk pesanannya.
        $menahanDana = [StatusPesanan::Dibayar, StatusPesanan::Dikemas, StatusPesanan::Dikirim];
        $ditahan = (int) Pesanan::query()->whereIn('status', $menahanDana)->sum('total_rupiah');
        $escrow = (int) Dompet::query()
            ->where('kode', JenisDompet::Escrow->value)
            ->value('saldo_rupiah');

        if ($escrow !== $ditahan) {
            $pelanggaran[] = "Saldo escrow {$escrow}, tetapi dana pesanan yang ditahan {$ditahan}.";
        }

        // 6. Dana yang ditahan untuk pencairan sama dengan pengajuan pencairan yang berstatus menunggu (LP-16).
        $menungguPencairan = (int) Pencairan::query()
            ->where('status', StatusPencairan::Menunggu)
            ->sum('jumlah_rupiah');
        $penahan = (int) Dompet::query()
            ->where('kode', JenisDompet::Penahan->value)
            ->value('saldo_rupiah');

        if ($penahan !== $menungguPencairan) {
            $pelanggaran[] = "Saldo penahan {$penahan}, tetapi dana pencairan yang menunggu {$menungguPencairan}.";
        }

        $this->table(['Dompet', 'Jenis', 'Saldo (Rp)'], $baris);

        if ($pelanggaran !== []) {
            $this->error('Pelanggaran invarian buku besar:');
            foreach ($pelanggaran as $p) {
                $this->line(" - {$p}");
            }

            return self::FAILURE;
        }

        $jumlahMutasi = MutasiSaldo::count();
        $this->info("Buku besar seimbang: {$jumlahMutasi} mutasi, 0 pelanggaran.");

        return self::SUCCESS;
    }
}

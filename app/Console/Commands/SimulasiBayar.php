<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Balapan\PanggilHttp;
use App\Models\Pembayaran;
use App\Services\TandaTanganGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class SimulasiBayar extends Command
{
    use PanggilHttp;

    protected $signature = 'lapak:simulasi-bayar
                            {kode : Kode tagihan (BYR-...)}
                            {--jumlah= : Jumlah rupiah yang dibayarkan}
                            {--ganda=1 : Berapa kali kabar dikirim}
                            {--rahasia= : Gunakan rahasia tertentu}';

    protected $description = 'Menyimulasikan panggilan webhook dari gerbang pembayaran';

    public function handle(TandaTanganGateway $tandaTangan): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini tidak boleh dijalankan di produksi.');

            return self::FAILURE;
        }

        $kode = (string) $this->argument('kode');
        $tagihan = Pembayaran::query()->where('kode', $kode)->first();

        if ($tagihan === null) {
            $this->error("Tagihan dengan kode [{$kode}] tidak ditemukan.");

            return self::FAILURE;
        }

        $jumlah = $this->option('jumlah') !== null
            ? (int) $this->option('jumlah')
            : $tagihan->jumlah_rupiah;

        $ganda = max(1, (int) $this->option('ganda'));
        $rahasia = $this->option('rahasia') ?: null;

        $this->info("Menyimulasikan pelunasan tagihan {$kode} sebesar Rp ".number_format($jumlah, 0, ',', '.'));

        for ($i = 1; $i <= $ganda; $i++) {
            $kabar = [
                'id_peristiwa' => 'sim_'.Str::ulid(),
                'kode_pembayaran' => $tagihan->kode,
                'jumlah_rupiah' => $jumlah,
            ];

            $badan = json_encode($kabar, JSON_UNESCAPED_SLASHES);
            $tanda = $tandaTangan->buat($badan, rahasia: $rahasia);

            $res = $this->panggilJson(
                'POST',
                '/api/v1/webhook/pembayaran',
                $kabar,
                [TandaTanganGateway::HEADER => $tanda],
            );

            $status = $res['status'];
            $data = $res['data'];

            if ($status === 200) {
                $hasil = $data['data']['hasil'] ?? 'ok';
                $this->line("Kirim #{$i}: [{$status}] Hasil: {$hasil}");
            } else {
                $pesan = $data['galat']['pesan'] ?? ($data['pesan'] ?? 'Galat');
                $kodeGalat = $data['galat']['kode'] ?? 'ERR';
                $this->warn("Kirim #{$i}: [{$status}] {$kodeGalat}: {$pesan}");
            }
        }

        return self::SUCCESS;
    }
}

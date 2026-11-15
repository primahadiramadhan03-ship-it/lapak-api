<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KunciIdempotensi;
use Illuminate\Console\Command;

/**
 * Membuang rekaman kunci idempotensi yang sudah melewati masa simpannya (LP-11).
 *
 * Rekaman ini hanya berguna selama klien masih mungkin mengirim ulang permintaan yang sama.
 * Menyimpannya selamanya membuat tabel tumbuh tanpa batas, sedangkan menghapus terlalu cepat
 * membuka jalan bagi eksekusi ganda.
 */
final class PangkasIdempotensi extends Command
{
    protected $signature = 'lapak:pangkas-idempotensi
                            {--hari= : Masa simpan dalam hari (bawaan dari config/lapak.php)}
                            {--uji-coba : Hitung saja, jangan hapus}';

    protected $description = 'Membuang rekaman kunci idempotensi yang lebih lama dari masa simpannya (LP-11).';

    public function handle(): int
    {
        $opsi = $this->option('hari');
        $hari = $opsi === null
            ? (int) config('lapak.idempotensi_simpan_hari', 7)
            : (int) $opsi;

        if ($hari < 1) {
            $this->error('Masa simpan minimal 1 hari; kunci yang lebih muda masih mungkin dikirim ulang.');

            return self::FAILURE;
        }

        $batas = now()->subDays($hari);

        $kueri = KunciIdempotensi::query()->where('created_at', '<', $batas);

        if ($this->option('uji-coba')) {
            $this->info("{$kueri->count()} rekaman lebih lama dari {$hari} hari akan dihapus (uji coba).");

            return self::SUCCESS;
        }

        $jumlah = $kueri->delete();

        $this->info("{$jumlah} rekaman kunci idempotensi lebih lama dari {$hari} hari dihapus.");

        return self::SUCCESS;
    }
}

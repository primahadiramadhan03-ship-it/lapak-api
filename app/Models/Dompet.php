<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisDompet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Dompet extends Model
{
    protected $table = 'dompet';

    // Sengaja kosong: dompet hanya dibuat/diubah oleh Service BukuBesar, bukan input klien
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'jenis' => JenisDompet::class,
            'saldo_rupiah' => 'integer',
        ];
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiSaldo::class, 'dompet_id');
    }

    /**
     * LP-13: Method khusus untuk membuat dompet baru tanpa mass assignment.
     * Hanya boleh dipanggil dari dalam kode (seeder, service), bukan dari input klien.
     */
    public static function buat(string $kode, JenisDompet $jenis, int $saldo = 0): self
    {
        $dompet = new self;
        $dompet->kode = $kode;
        $dompet->jenis = $jenis;
        $dompet->saldo_rupiah = $saldo;
        $dompet->save();

        return $dompet;
    }
}

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
}

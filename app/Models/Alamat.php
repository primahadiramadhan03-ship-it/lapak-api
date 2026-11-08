<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AlamatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Alamat extends Model
{
    /** @use HasFactory<AlamatFactory> */
    use HasFactory;

    protected $table = 'alamat';

    protected $fillable = [
        'label',
        'nama_penerima',
        'telepon',
        'jalan',
        'kota',
        'kode_pos',
    ];

    protected function casts(): array
    {
        return [
            'utama' => 'boolean',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class);
    }

    public function scopeMilik(Builder $query, Pengguna $pengguna): Builder
    {
        return $query->where('pengguna_id', $pengguna->id);
    }

    /**
     * Potret alamat untuk kebutuhan snapshot pesanan.
     *
     * @return array<string, string>
     */
    public function potret(): array
    {
        return [
            'nama_penerima' => $this->nama_penerima,
            'telepon' => $this->telepon,
            'jalan' => $this->jalan,
            'kota' => $this->kota,
            'kode_pos' => $this->kode_pos,
        ];
    }
}

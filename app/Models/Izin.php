<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NamaIzin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Izin extends Model
{
    protected $table = 'izin';

    protected $fillable = ['nama', 'keterangan'];

    protected function casts(): array
    {
        return [
            'nama' => NamaIzin::class,
        ];
    }

    /** @return BelongsToMany<Peran, $this> */
    public function peran(): BelongsToMany
    {
        return $this->belongsToMany(Peran::class, 'peran_izin', 'izin_id', 'peran_id')
            ->withTimestamps();
    }
}

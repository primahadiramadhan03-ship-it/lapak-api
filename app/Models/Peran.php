<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NamaPeran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Peran extends Model
{
    protected $table = 'peran';

    protected $fillable = ['nama', 'keterangan'];

    protected function casts(): array
    {
        return [
            'nama' => NamaPeran::class,
        ];
    }

    /** @return BelongsToMany<Izin, $this> */
    public function izin(): BelongsToMany
    {
        return $this->belongsToMany(Izin::class, 'peran_izin', 'peran_id', 'izin_id')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Pengguna, $this> */
    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(Pengguna::class, 'pengguna_peran', 'peran_id', 'pengguna_id')
            ->withPivot('diberikan_oleh')
            ->withTimestamps();
    }
}

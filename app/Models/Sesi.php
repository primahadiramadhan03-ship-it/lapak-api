<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use LogicException;

final class Sesi extends Model
{
    protected $table = 'sesi';

    // Sesi hanya dibuat dan diubah PenerbitSesi.
    protected $fillable = [];

    protected $hidden = ['hash_kunci'];

    protected function casts(): array
    {
        return [
            'dikonfirmasi_pada' => 'datetime',
            'terakhir_dipakai_pada' => 'datetime',
            'kedaluwarsa_pada' => 'datetime',
            'dicabut_pada' => 'datetime',
        ];
    }

    /** @return BelongsTo<Pengguna, $this> */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * LP-14: sesi berlaku bila belum dicabut, belum kedaluwarsa,
     * dan belum melewati batas waktu menganggur.
     *
     * @param  Builder<Sesi>  $kueri
     */
    public function scopeBerlaku(Builder $kueri): void
    {
        $batasMenganggur = now()->subMinutes(
            config('lapak.sesi.menganggur_menit')
        );

        $kueri->whereNull('dicabut_pada')
            ->where('kedaluwarsa_pada', '>', now())
            ->where('terakhir_dipakai_pada', '>', $batasMenganggur);
    }

    /** LP-15: kata sandi dibuktikan dalam beberapa menit terakhir. */
    public function segar(): bool
    {
        return $this->dikonfirmasi_pada !== null
            && $this->dikonfirmasi_pada->gt(
                now()->subMinutes(config('lapak.sesi.segar_menit'))
            );
    }

    /**
     * Mengambil sesi yang digunakan permintaan saat ini.
     * Hanya sah di belakang middleware auth:sesi.
     */
    public static function dariPermintaan(Request $request): self
    {
        return $request->attributes->get('sesi')
            ?? throw new LogicException(
                'Rute ini memakai sesi tetapi tidak dilindungi auth:sesi.'
            );
    }
}

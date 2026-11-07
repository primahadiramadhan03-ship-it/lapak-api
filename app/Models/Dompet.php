<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisDompet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Dompet extends Model
{
    protected $table = 'dompet';

    // Sengaja kosong: dompet hanya dibuat/diubah oleh Service BukuBesar, bukan input klien
    protected $fillable = [];

    // Flag privat untuk mengizinkan update saldo sementara dari method ubahSaldo()
    private bool $bolehUbahSaldo = false;

    protected function casts(): array
    {
        return [
            'jenis' => JenisDompet::class,
            'saldo_rupiah' => 'integer',
        ];
    }

    /**
     * Null untuk dompet sistem (eksternal, escrow, pendapatan).
     *
     * @return BelongsTo<Pengguna, $this>
     */
    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiSaldo::class, 'dompet_id');
    }

    /**
     * LP-13: Proteksi agar dompet tidak bisa dihapus.
     */
    protected static function booted(): void
    {
        self::deleting(fn () => throw new LogicException('LP-13: dompet tidak boleh dihapus.'));

        self::updating(function (self $dompet) {
            // Jika yang diubah hanya saldo_rupiah DAN flag tidak aktif → tolak
            if ($dompet->isDirty('saldo_rupiah') && ! $dompet->bolehUbahSaldo) {
                throw new LogicException('LP-13: saldo dompet hanya boleh diubah melalui method ubahSaldo().');
            }
        });
    }

    /**
     * LP-13: Method khusus untuk mengubah saldo dompet.
     * Hanya boleh dipanggil dari dalam kode (service BukuBesar), bukan dari input klien.
     */
    public function ubahSaldo(int $saldoBaru): void
    {
        // Cek aturan LP-13: hanya dompet eksternal yang boleh negatif
        if (! $this->jenis->bolehNegatif() && $saldoBaru < 0) {
            throw new LogicException('LP-13: dompet '.$this->jenis->value.' tidak boleh bersaldo negatif.');
        }

        $this->bolehUbahSaldo = true;
        $this->saldo_rupiah = $saldoBaru;
        $this->save();
        $this->bolehUbahSaldo = false;
    }

    /**
     * LP-13: Method khusus untuk membuat dompet baru tanpa mass assignment.
     */
    public static function buat(string $kode, JenisDompet $jenis, int $saldo = 0, ?int $penggunaId = null): self
    {
        $dompet = new self;
        $dompet->kode = $kode;
        $dompet->jenis = $jenis;
        $dompet->saldo_rupiah = $saldo;
        if ($penggunaId !== null) {
            $dompet->pengguna_id = $penggunaId;
        }
        $dompet->save();

        return $dompet;
    }
}

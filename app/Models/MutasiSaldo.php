<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JenisMutasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MutasiSaldo extends Model
{
    public const UPDATED_AT = null; // LP-13: Tidak boleh ada kolom updated_at

    protected $table = 'mutasi_saldo';
    protected $fillable = [];

    protected function casts(): array 
    {
        return [
            'jenis' => JenisMutasi::class,
            'jumlah_rupiah' => 'integer',
            'saldo_setelah' => 'integer',
        ];
    }

    // LP-13: Proteksi agar mutasi tidak bisa diubah atau dihapus
    protected static function booted(): void 
    {
        self::updating(fn() => throw new LogicException('LP-13: mutasi saldo tidak boleh diubah.'));
        self::deleting(fn() => throw new LogicException('LP-13: mutasi saldo tidak boleh dihapus.'));
    }

    public function dompet(): BelongsTo 
    {
        return $this->belongsTo(Dompet::class, 'dompet_id');
    }

    /**
     * LP-13: Method khusus untuk membuat mutasi baru tanpa mass assignment.
     * Hanya boleh dipanggil dari dalam kode (service BukuBesar), bukan dari input klien.
     */
    public static function buat(
        int $dompetId,
        string $kelompok,
        JenisMutasi $jenis,
        int $jumlahRupiah,
        int $saldoSetelah,
        string $rujukan,
        ?string $keterangan = null
    ): self {
        $mutasi = new self();
        $mutasi->dompet_id = $dompetId;
        $mutasi->kelompok = $kelompok;
        $mutasi->jenis = $jenis;
        $mutasi->jumlah_rupiah = $jumlahRupiah;
        $mutasi->saldo_setelah = $saldoSetelah;
        $mutasi->rujukan = $rujukan;
        $mutasi->keterangan = $keterangan;
        $mutasi->save();
        return $mutasi;
    }
}
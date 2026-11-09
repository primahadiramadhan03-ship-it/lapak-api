<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pesanan\StatusPesanan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pesanan extends Model
{
    use HasFactory;

    protected $table = 'pesanan';

    protected $fillable = [
        'checkout_id',
        'nomor',
        'pembeli_id',
        'toko_id',
        'status',
        'subtotal_rupiah',
        'ongkir_rupiah',
        'total_rupiah',
        'alamat_kirim',
        'dibayar_pada',
        'dibatalkan_pada',
        'alasan_batal',
    ];

    protected $casts = [
        'status' => StatusPesanan::class,
        'alamat_kirim' => 'array',
        'dibayar_pada' => 'immutable_datetime',
        'dibatalkan_pada' => 'immutable_datetime',
    ];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class, 'checkout_id');
    }

    public function pembeli(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pembeli_id');
    }

    public function toko(): BelongsTo
    {
        return $this->belongsTo(Toko::class, 'toko_id');
    }

    public function butir(): HasMany
    {
        return $this->hasMany(ButirPesanan::class, 'pesanan_id');
    }
}

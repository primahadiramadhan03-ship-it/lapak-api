<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkout extends Model
{
    use HasFactory;

    protected $table = 'checkout';

    protected $fillable = [
        'kode',
        'pembeli_id',
        'total_rupiah',
        'pengenal_sesi',
    ];

    public function pembeli(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pembeli_id');
    }

    public function pesanan(): HasMany
    {
        return $this->hasMany(Pesanan::class, 'checkout_id');
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPembayaran;
use App\Models\Checkout;
use App\Models\Pembayaran;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pembayaran>
 */
class PembayaranFactory extends Factory
{
    protected $model = Pembayaran::class;

    public function definition(): array
    {
        return [
            'checkout_id' => Checkout::factory(),
            'kode' => 'BYR-'.Str::ulid(),
            'jumlah_rupiah' => 100_000,
            'status' => StatusPembayaran::Menunggu,
            'batas_bayar_pada' => now()->addHours(config('lapak.batas_bayar_jam', 24)),
            'lunas_pada' => null,
            'id_peristiwa' => null,
        ];
    }

    public function lunas(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Lunas,
            'lunas_pada' => now(),
            'id_peristiwa' => (string) Str::uuid(),
        ]);
    }

    public function kedaluwarsa(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Kedaluwarsa,
            'batas_bayar_pada' => now()->subMinute(),
        ]);
    }

    public function dibatalkan(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPembayaran::Dibatalkan,
        ]);
    }
}

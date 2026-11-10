<?php

namespace Database\Factories;

use App\Models\Checkout;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CheckoutFactory extends Factory
{
    protected $model = Checkout::class;

    public function definition(): array
    {
        return [
            'kode' => (string) Str::ulid(),
            'pembeli_id' => Pengguna::factory(),
            'total_rupiah' => 100_000,
            'pengenal_sesi' => (string) Str::ulid(),
        ];
    }
}

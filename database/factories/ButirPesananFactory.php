<?php

namespace Database\Factories;

use App\Models\ButirPesanan;
use App\Models\Pesanan;
use App\Models\Produk;
use Illuminate\Database\Eloquent\Factories\Factory;

class ButirPesananFactory extends Factory
{
    protected $model = ButirPesanan::class;

    public function definition(): array
    {
        return [
            'pesanan_id' => Pesanan::factory(),
            'produk_id' => Produk::factory(),
            'nama_produk' => 'Buku Pemrograman',
            'harga_rupiah' => 45_000,
            'kuantitas' => 2,
            'subtotal_rupiah' => 90_000,
        ];
    }
}

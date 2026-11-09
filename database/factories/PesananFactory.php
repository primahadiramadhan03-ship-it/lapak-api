<?php

namespace Database\Factories;

use App\Domain\Pesanan\PenomorPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Models\Checkout;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Toko;
use Illuminate\Database\Eloquent\Factories\Factory;

class PesananFactory extends Factory
{
    protected $model = Pesanan::class;

    public function definition(): array
    {
        return [
            'checkout_id' => Checkout::factory(),
            'nomor' => null,
            'pembeli_id' => Pengguna::factory(),
            'toko_id' => Toko::factory(),
            'status' => StatusPesanan::MenungguPembayaran,
            'subtotal_rupiah' => 90_000,
            'ongkir_rupiah' => 10_000,
            'total_rupiah' => 100_000,
            'alamat_kirim' => [
                'nama_penerima' => 'Budi Santoso',
                'telepon' => '081234567890',
                'alamat_lengkap' => 'Jl. Merdeka No. 10',
                'kota' => 'Surakarta',
                'kode_pos' => '57126',
            ],
            'dibayar_pada' => null,
            'dibatalkan_pada' => null,
            'alasan_batal' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Pesanan $pesanan) {
            if ($pesanan->nomor === null) {
                $pesanan->updateQuietly([
                    'nomor' => PenomorPesanan::buat($pesanan->id),
                ]);
            }
        });
    }

    public function status(StatusPesanan $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}

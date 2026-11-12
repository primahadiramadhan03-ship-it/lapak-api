<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPencairan;
use App\Models\Pencairan;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Pencairan>
 */
class PencairanFactory extends Factory
{
    protected $model = Pencairan::class;

    public function definition(): array
    {
        return [
            'kode' => 'CAIR-'.Str::ulid(),
            'pengaju_id' => Pengguna::factory()->penjual(),
            'jumlah_rupiah' => 50_000,
            'rekening_tujuan' => 'BCA 1234567890 a.n. Sri Wahyuni',
            'status' => StatusPencairan::Menunggu,
            'diputuskan_oleh' => null,
            'diputuskan_pada' => null,
            'catatan' => null,
        ];
    }

    public function disetujui(?Pengguna $pemutus = null, ?string $catatan = null): static
    {
        return $this->state(fn () => [
            'status' => StatusPencairan::Disetujui,
            'diputuskan_oleh' => $pemutus?->id ?? Pengguna::factory()->keuangan(),
            'diputuskan_pada' => now(),
            'catatan' => $catatan,
        ]);
    }

    public function ditolak(string $catatan = 'Data rekening tidak sesuai', ?Pengguna $pemutus = null): static
    {
        return $this->state(fn () => [
            'status' => StatusPencairan::Ditolak,
            'diputuskan_oleh' => $pemutus?->id ?? Pengguna::factory()->keuangan(),
            'diputuskan_pada' => now(),
            'catatan' => $catatan,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JenisDompet;
use App\Enums\StatusPencairan;
use App\Models\Dompet;
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
            'kode' => 'PCR-'.Str::ulid(),
            'pengguna_id' => Pengguna::factory()->penjual(),
            'dompet_id' => function (array $atribut) {
                $kode = 'penjual:'.$atribut['pengguna_id'];

                return Dompet::query()->where('kode', $kode)->first()?->id
                    ?? Dompet::buat($kode, JenisDompet::Penjual, 0, $atribut['pengguna_id'])->id;
            },
            'jumlah_rupiah' => 50_000,
            'rekening_tujuan' => 'BCA 1234567890 a.n. Sri Wahyuni',
            'status' => StatusPencairan::Menunggu,
            'diputuskan_oleh' => null,
            'alasan_tolak' => null,
            'diputuskan_pada' => null,
        ];
    }

    public function disetujui(?Pengguna $pemutus = null): static
    {
        return $this->state(fn () => [
            'status' => StatusPencairan::Disetujui,
            'diputuskan_oleh' => $pemutus?->id ?? Pengguna::factory()->keuangan(),
            'diputuskan_pada' => now(),
        ]);
    }

    public function ditolak(string $alasan = 'Data rekening tidak sesuai', ?Pengguna $pemutus = null): static
    {
        return $this->state(fn () => [
            'status' => StatusPencairan::Ditolak,
            'diputuskan_oleh' => $pemutus?->id ?? Pengguna::factory()->keuangan(),
            'alasan_tolak' => $alasan,
            'diputuskan_pada' => now(),
        ]);
    }
}

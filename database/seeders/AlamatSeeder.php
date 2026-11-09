<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Alamat;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

final class AlamatSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn(
                'AlamatSeeder dilewati: data contoh tidak boleh ada di produksi.'
            );

            return;
        }

        $daftarAlamat = [
            'andi@lapak.test' => [
                [
                    'label' => 'Rumah',
                    'nama_penerima' => 'Andi Nugroho',
                    'telepon' => '081200000008',
                    'jalan' => 'Jl. Slamet Riyadi No. 12',
                    'kota' => 'Surakarta',
                    'kode_pos' => '57111',
                    'utama' => true,
                ],
                [
                    'label' => 'Kantor',
                    'nama_penerima' => 'Andi Nugroho',
                    'telepon' => '081200000008',
                    'jalan' => 'Jl. Adi Sucipto No. 25',
                    'kota' => 'Surakarta',
                    'kode_pos' => '57121',
                    'utama' => false,
                ],
            ],
            'dewi@lapak.test' => [
                [
                    'label' => 'Rumah',
                    'nama_penerima' => 'Dewi Anggraini',
                    'telepon' => '081200000009',
                    'jalan' => 'Jl. Dr. Rajiman No. 18',
                    'kota' => 'Surakarta',
                    'kode_pos' => '57141',
                    'utama' => true,
                ],
            ],
            'sri@lapak.test' => [
                [
                    'label' => 'Rumah',
                    'nama_penerima' => 'Sri Wahyuni',
                    'telepon' => '081200000010',
                    'jalan' => 'Jl. Veteran No. 32',
                    'kota' => 'Surakarta',
                    'kode_pos' => '57116',
                    'utama' => true,
                ],
            ],
        ];

        foreach ($daftarAlamat as $surel => $alamatPengguna) {
            $pengguna = Pengguna::query()
                ->where('surel', $surel)
                ->first();

            if (! $pengguna) {
                continue;
            }

            foreach ($alamatPengguna as $data) {
                $alamat = Alamat::query()->firstOrNew([
                    'pengguna_id' => $pengguna->id,
                    'label' => $data['label'],
                ]);

                $alamat->forceFill([
                    'pengguna_id' => $pengguna->id,
                    ...$data,
                ])->save();
            }
        }
    }
}

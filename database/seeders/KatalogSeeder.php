<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Toko;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class KatalogSeeder extends Seeder
{
    /**
     * @return void
     */
    public function run(): void
    {
        $kategori = Kategori::factory()->count(5)->create();

        $pengguna = Pengguna::factory()->create([
            'nama' => 'Bapak UMKM',
            'surel' => 'umkm@lapak.test',
        ]);

        $toko = Toko::factory()->for($pengguna, 'pemilik')->create([
            'nama' => 'Warung Bu Tini',
            'slug' => 'warung-bu-tini',
            'kota' => 'Surakarta',
            'status' => 'aktif',
        ]);

        foreach ($kategori as $kat) {
            Produk::factory()->count(3)->for($toko)->for($kat)->create();
        }
    }
}

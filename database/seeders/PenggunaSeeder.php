<?php

namespace Database\Seeders;

use App\Enums\StatusAkun;
use App\Models\Pengguna;
use Illuminate\Database\Seeder;

class PenggunaSeeder extends Seeder
{
    public function run(): void
    {
        Pengguna::factory()->create([
            'nama' => 'Prima Hadi Ramadhan',
            'surel' => 'prima@example.test',
            'status' => StatusAkun::Aktif,
        ]);
    }
}

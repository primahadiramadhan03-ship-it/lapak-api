<?php

declare(strict_types=1);

namespace Tests\Feature\Dompet;

use App\Enums\JenisDompet;
use App\Models\Dompet;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SaldoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_bisa_melihat_saldo_dompetnya(): void
    {
        $pengguna = Pengguna::factory()->create();
        $dompet = Dompet::buat('user:'.$pengguna->id, JenisDompet::Pribadi, 150000);
        $pengguna->dompet()->save($dompet);

        $response = $this->sebagai($pengguna)
            ->getJson('/api/v1/dompet/saya');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'kode' => 'user:'.$pengguna->id,
                    'jenis' => 'pribadi',
                    'saldo_rupiah' => 150000,
                ],
            ]);
    }

    public function test_pengguna_tanpa_sesi_tidak_bisa_melihat_saldo(): void
    {
        $response = $this->getJson('/api/v1/dompet/saya');

        $response->assertStatus(401);
    }
}

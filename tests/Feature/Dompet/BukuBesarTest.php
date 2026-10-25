<?php

declare(strict_types=1);

namespace Tests\Feature\Dompet;

use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Exceptions\SaldoTidakCukup;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Services\BukuBesar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BukuBesarTest extends TestCase
{
    use RefreshDatabase;

    private BukuBesar $bukuBesar;

    private Dompet $dompetA;

    private Dompet $dompetB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bukuBesar = new BukuBesar;

        // Buat 2 dompet untuk testing
        $this->dompetA = Dompet::buat('penjual:1', JenisDompet::Penjual, 100000);
        $this->dompetB = Dompet::buat('penjual:2', JenisDompet::Penjual, 50000);
    }

    public function test_bisa_memindahkan_saldo_antar_dompet(): void
    {
        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            30000,
            JenisMutasi::DanaMasuk,
            'TEST-001',
            'Transfer test'
        );

        // Refresh dari database
        $this->dompetA->refresh();
        $this->dompetB->refresh();

        // LP-13: Saldo dompet A berkurang
        $this->assertEquals(70000, $this->dompetA->saldo_rupiah);
        // LP-13: Saldo dompet B bertambah
        $this->assertEquals(80000, $this->dompetB->saldo_rupiah);

        // LP-13: Ada 2 mutasi yang tercipta
        $mutasi = MutasiSaldo::where('rujukan', 'TEST-001')->get();
        $this->assertCount(2, $mutasi);

        // LP-13: Jumlah mutasi harus nol (satu negatif, satu positif)
        $total = $mutasi->sum('jumlah_rupiah');
        $this->assertEquals(0, $total);
    }

    public function test_gagal_jika_saldo_tidak_cukup(): void
    {
        $this->expectException(SaldoTidakCukup::class);

        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            200000, // Lebih besar dari saldo 100000
            JenisMutasi::DanaMasuk,
            'TEST-002'
        );
    }

    public function test_dompet_eksternal_boleh_negatif(): void
    {
        // Buat dompet eksternal
        $dompetEksternal = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);

        // LP-13: Dompet eksternal boleh negatif
        $this->bukuBesar->pindahkan(
            $dompetEksternal,
            $this->dompetA,
            50000,
            JenisMutasi::DanaMasuk,
            'TEST-003'
        );

        $dompetEksternal->refresh();
        $this->assertEquals(-50000, $dompetEksternal->saldo_rupiah);
    }

    public function test_periksa_keseimbangan_buku_besar(): void
    {
        // Pindahkan saldo
        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            25000,
            JenisMutasi::DanaMasuk,
            'TEST-004'
        );

        // LP-13: Buku besar harus seimbang
        $this->assertTrue($this->bukuBesar->periksaKeseimbangan());
    }

    public function test_gagal_jika_jumlah_negatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            -10000, // Negatif tidak diperbolehkan
            JenisMutasi::DanaMasuk,
            'TEST-005'
        );
    }
}

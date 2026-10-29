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
use LogicException; // ← TAMBAHKAN INI DI BAGIAN ATAS
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
        $this->bukuBesar = new BukuBesar();

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

        $this->dompetA->refresh();
        $this->dompetB->refresh();

        $this->assertEquals(70000, $this->dompetA->saldo_rupiah);
        $this->assertEquals(80000, $this->dompetB->saldo_rupiah);

        $mutasi = MutasiSaldo::where('rujukan', 'TEST-001')->get();
        $this->assertCount(2, $mutasi);

        $total = $mutasi->sum('jumlah_rupiah');
        $this->assertEquals(0, $total);
    }

    public function test_gagal_jika_saldo_tidak_cukup(): void
    {
        $this->expectException(SaldoTidakCukup::class);

        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            200000,
            JenisMutasi::DanaMasuk,
            'TEST-002'
        );
    }

    public function test_dompet_eksternal_boleh_negatif(): void
    {
        $dompetEksternal = Dompet::buat('eksternal', JenisDompet::Eksternal, 0);

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
        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            25000,
            JenisMutasi::DanaMasuk,
            'TEST-004'
        );

        $this->assertTrue($this->bukuBesar->periksaKeseimbangan());
    }

    public function test_gagal_jika_jumlah_negatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->bukuBesar->pindahkan(
            $this->dompetA,
            $this->dompetB,
            -10000,
            JenisMutasi::DanaMasuk,
            'TEST-005'
        );
    }

   

    public function test_update_dompet_melemper_exception(): void
    {
        $this->expectException(LogicException::class);

        // LP-13: Update langsung saldo dompet harus ditolak
        $this->dompetA->saldo_rupiah = 999999;
        $this->dompetA->save();
    }

    public function test_delete_dompet_melemper_exception(): void
    {
        $this->expectException(LogicException::class);

        // LP-13: Delete dompet harus ditolak
        $this->dompetA->delete();
    }
} 
<?php

declare(strict_types=1);

use App\Enums\NamaPeran;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Sesi;
use App\Services\PemeriksaSesi;
use App\Services\PenerbitSesi;
use App\Services\PengelolaPeran;
use Database\Seeders\PeranIzinSeeder;

beforeEach(function () {
    $this->seed(PeranIzinSeeder::class);
    $this->pengguna = Pengguna::factory()->create([
        'surel' => 'prima@lapak.test',
        'kata_sandi' => 'BatikSolo2026',
    ]);
});

it('mencatat audit saat masuk berhasil', function () {
    $this->postJson('/api/v1/masuk', [
        'surel' => 'prima@lapak.test',
        'kata_sandi' => 'BatikSolo2026',
        'nama_perangkat' => 'Laptop Prima',
    ])->assertCreated();

    $log = LogAudit::query()->where('peristiwa', PeristiwaAudit::MasukBerhasil->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->pelaku_id)->toBe($this->pengguna->id)
        ->and($log->rincian['nama_perangkat'])->toBe('Laptop Prima');
});

it('mencatat audit saat masuk gagal dan tidak membocorkan kata sandi', function () {
    $this->postJson('/api/v1/masuk', [
        'surel' => 'prima@lapak.test',
        'kata_sandi' => 'SandiSalah123',
        'nama_perangkat' => 'Laptop Prima',
    ])->assertUnauthorized();

    $log = LogAudit::query()->where('peristiwa', PeristiwaAudit::MasukGagal->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->pelaku_id)->toBeNull()
        ->and($log->rincian['surel'])->toBe('prima@lapak.test')
        ->and($log->rincian)->not->toHaveKey('kata_sandi');
});

it('mencatat audit saat sesi dicabut oleh pengguna', function () {
    [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->pengguna, 'Laptop Prima');

    $this->denganKunci($kunci)->postJson('/api/v1/keluar')->assertOk();

    $log = LogAudit::query()->where('peristiwa', PeristiwaAudit::SesiDicabut->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->pelaku_id)->toBe($this->pengguna->id)
        ->and($log->sasaran)->toBe("sesi:{$sesi->pengenal}");
});

it('mendeteksi pemakaian kunci lama, mencabut sesi, dan mencatat audit', function () {
    $penerbit = app(PenerbitSesi::class);
    $pemeriksa = app(PemeriksaSesi::class);

    [$sesi, $kunciLama] = $penerbit->terbitkan($this->pengguna, 'Laptop Prima');
    $kunciBaru = $penerbit->rotasi($sesi);

    // Pemakaian kunci lama
    expect($pemeriksa->periksa($kunciLama))->toBeNull();

    // Sesi harus tercabut
    expect($sesi->refresh()->dicabut_pada)->not->toBeNull();

    // Kunci baru tidak lagi berlaku karena sesinya dicabut
    expect($pemeriksa->periksa($kunciBaru))->toBeNull();

    $log = LogAudit::query()->where('peristiwa', PeristiwaAudit::KunciLamaDipakai->value)->first();

    expect($log)->not->toBeNull()
        ->and($log->pelaku_id)->toBe($this->pengguna->id)
        ->and($log->sasaran)->toBe("sesi:{$sesi->pengenal}");
});

it('mencatat audit saat peran diberikan dan dicabut', function () {
    $pengelola = app(PengelolaPeran::class);
    $admin = Pengguna::factory()->admin()->create();

    // Ganti StafKeuangan -> Keuangan
    $pengelola->beri($this->pengguna, NamaPeran::Keuangan, $admin);

    $logBeri = LogAudit::query()->where('peristiwa', PeristiwaAudit::PeranDiberikan->value)->first();
    expect($logBeri)->not->toBeNull()
        ->and($logBeri->pelaku_id)->toBe($admin->id)
        ->and($logBeri->sasaran)->toBe("pengguna:{$this->pengguna->id}")
        ->and($logBeri->rincian['peran'])->toBe(NamaPeran::Keuangan->value);

    // Ganti StafKeuangan -> Keuangan
    $pengelola->cabut($this->pengguna, NamaPeran::Keuangan, $admin);

    $logCabut = LogAudit::query()->where('peristiwa', PeristiwaAudit::PeranDicabut->value)->first();
    expect($logCabut)->not->toBeNull()
        ->and($logCabut->pelaku_id)->toBe($admin->id)
        ->and($logCabut->sasaran)->toBe("pengguna:{$this->pengguna->id}")
        ->and($logCabut->rincian['peran'])->toBe(NamaPeran::Keuangan->value);
});

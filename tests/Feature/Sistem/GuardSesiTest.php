<?php

use App\Enums\StatusAkun;
use App\Exceptions\SesiTidakSegar;
use App\Http\Middleware\SesiSegar;
use App\Models\Pengguna;
use App\Services\PemeriksaSesi;
use App\Services\PenerbitSesi;

describe('Guard Sesi', function () {
    beforeEach(function () {
        $this->andi = Pengguna::factory()->create(['status' => StatusAkun::Aktif]);
    });

    it('PemeriksaSesi menolak kunci kosong', function () {
        $pemeriksaSesi = app(PemeriksaSesi::class);
        expect($pemeriksaSesi->periksa(null))->toBeNull();
    });

    it('PemeriksaSesi menolak sesi yang dicabut', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        app(PenerbitSesi::class)->cabut($sesi);
        $pemeriksaSesi = app(PemeriksaSesi::class);
        expect($pemeriksaSesi->periksa($kunci))->toBeNull();
    });

    it('PemeriksaSesi menolak sesi kedaluwarsa', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $sesi->forceFill(['kedaluwarsa_pada' => now()->subSecond()])->save();
        $pemeriksaSesi = app(PemeriksaSesi::class);
        expect($pemeriksaSesi->periksa($kunci))->toBeNull();
    });

    it('PemeriksaSesi menolak sesi menganggur terlalu lama', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $batasMenganggur = now()->subMinutes(config('lapak.sesi.menganggur_menit') + 1);
        $sesi->forceFill(['terakhir_dipakai_pada' => $batasMenganggur])->save();
        $pemeriksaSesi = app(PemeriksaSesi::class);
        expect($pemeriksaSesi->periksa($kunci))->toBeNull();
    });

    it('PemeriksaSesi menolak akun ditangguhkan', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $this->andi->forceFill(['status' => StatusAkun::Ditangguhkan])->save();
        $pemeriksaSesi = app(PemeriksaSesi::class);
        expect($pemeriksaSesi->periksa($kunci))->toBeNull();
    });

    it('PemeriksaSesi menerima kunci sah', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $pemeriksaSesi = app(PemeriksaSesi::class);
        $hasilPeriksa = $pemeriksaSesi->periksa($kunci);
        expect($hasilPeriksa)->not->toBeNull();
        expect($hasilPeriksa->id)->toBe($sesi->id);
    });

    it('Sesi::segar() mengembalikan true untuk sesi baru', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        expect($sesi->segar())->toBeTrue();
    });

    it('Sesi::segar() mengembalikan false untuk sesi yang tidak dikonfirmasi ulang', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat');
        $sesi->forceFill(['dikonfirmasi_pada' => now()->subMinutes(config('lapak.sesi.segar_menit') + 1)])->save();
        expect($sesi->segar())->toBeFalse();
    });

    it('SesiTidakSegar exception memiliki LP-15 dan rincian konfirmasi', function () {
        $exception = new SesiTidakSegar;
        expect($exception->kode)->toBe('LP-15');
        expect($exception->statusHttp)->toBe(403);
        expect($exception->rincian['konfirmasi'])->toBe('POST /api/v1/sesi/konfirmasi');
    });

    it('SesiSegar middleware terdaftar di bootstrap', function () {
        expect(class_exists(SesiSegar::class))->toBeTrue();
    });
});

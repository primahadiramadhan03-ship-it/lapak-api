<?php

use App\Models\Pengguna;
use App\Models\Sesi;
use App\Services\PangkasSesi;
use App\Services\PenerbitSesi;

describe('Pangkas Sesi', function () {
    beforeEach(function () {
        $this->andi = Pengguna::factory()->create();
        $this->pangkas = app(PangkasSesi::class);
    });

    it('hapus sesi kedaluwarsa', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat 1');
        $sesi->forceFill(['kedaluwarsa_pada' => now()->subSecond()])->save();

        $hapus = $this->pangkas->hapusSesiKedaluwarsa();

        expect($hapus)->toBe(1);
        expect(Sesi::find($sesi->id))->toBeNull();
    });

    it('hapus sesi menganggur terlalu lama', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat 2');
        $batasMenganggur = now()->subMinutes(config('lapak.sesi.menganggur_menit') + 1);
        $sesi->forceFill(['terakhir_dipakai_pada' => $batasMenganggur])->save();

        $hapus = $this->pangkas->hapusSesiMenganggur();

        expect($hapus)->toBe(1);
        expect(Sesi::find($sesi->id))->toBeNull();
    });

    it('jalankan kedua pembersihan sekaligus', function () {
        [$sesi1, $kunci1] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat 1');
        [$sesi2, $kunci2] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat 2');

        $sesi1->forceFill(['kedaluwarsa_pada' => now()->subSecond()])->save();
        $sesi2->forceFill(['terakhir_dipakai_pada' => now()->subMinutes(config('lapak.sesi.menganggur_menit') + 1)])->save();

        $hapus = $this->pangkas->jalankan();

        expect($hapus)->toBe(2);
    });

    it('jangan hapus sesi yang masih berlaku', function () {
        [$sesi, $kunci] = app(PenerbitSesi::class)->terbitkan($this->andi, 'Perangkat Valid');

        $hapus = $this->pangkas->jalankan();

        expect($hapus)->toBe(0);
        expect(Sesi::find($sesi->id))->not->toBeNull();
    });
});

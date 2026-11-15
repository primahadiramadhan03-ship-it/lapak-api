<?php

declare(strict_types=1);

use App\Domain\Audit\PemeriksaRantaiAudit;
use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->pencatat = app(PencatatAudit::class);
    $this->pemeriksa = app(PemeriksaRantaiAudit::class);
    $this->admin = Pengguna::factory()->admin()->create();

    foreach ([1, 2, 3] as $nomor) {
        $this->pencatat->catat(
            PeristiwaAudit::PembayaranLunas,
            $this->admin,
            "pesanan:LPK-{$nomor}",
            ['nomor' => $nomor],
        );
    }

    // Nomor baris tidak selalu mulai dari 1: pada MySQL tabel yang sama dipakai lintas uji.
    $this->id = LogAudit::query()->orderBy('id')->pluck('id')->all();
});

it('menyatakan rantai utuh pada jejak yang ditulis PencatatAudit', function () {
    $laporan = $this->pemeriksa->periksa();

    expect($laporan['utuh'])->toBeTrue()
        ->and($laporan['jumlah'])->toBe(3)
        ->and($laporan['pelanggaran'])->toBe([]);

    $this->artisan('lapak:verifikasi-audit')
        ->expectsOutputToContain('UTUH')
        ->assertSuccessful();
});

it('menyatakan rantai utuh ketika belum ada satu baris pun', function () {
    DB::table('log_audit')->delete();
    DB::table('rantai_audit')->where('id', 1)->update([
        'hash_terakhir' => RantaiHash::awal(),
        'jumlah' => 0,
    ]);

    expect($this->pemeriksa->periksa()['utuh'])->toBeTrue();

    $this->artisan('lapak:verifikasi-audit')->assertSuccessful();
});

it('menemukan baris yang isinya diubah lewat basis data', function () {
    // Persis yang dapat dilakukan orang yang memegang akses basis data: melewati model.
    DB::table('log_audit')->where('id', $this->id[1])->update([
        'rincian' => json_encode(['nomor' => 999], JSON_THROW_ON_ERROR),
    ]);

    $laporan = $this->pemeriksa->periksa();

    expect($laporan['utuh'])->toBeFalse()
        ->and($laporan['pelanggaran'])->toContain("Baris {$this->id[1]}: isi baris tidak cocok dengan hash yang tersimpan.");

    $this->artisan('lapak:verifikasi-audit')
        ->expectsOutputToContain('RUSAK')
        ->assertFailed();
});

it('menemukan rantai yang terputus karena baris tengah dihapus', function () {
    DB::table('log_audit')->where('id', $this->id[1])->delete();

    $laporan = $this->pemeriksa->periksa();

    expect($laporan['utuh'])->toBeFalse()
        ->and($laporan['pelanggaran'])->toContain("Baris {$this->id[2]}: hash_sebelumnya tidak menyambung ke baris sebelumnya.");

    $this->artisan('lapak:verifikasi-audit')->assertFailed();
});

it('menemukan kepala rantai yang tidak lagi menunjuk baris terakhir', function () {
    DB::table('rantai_audit')->where('id', 1)->update([
        'hash_terakhir' => str_repeat('a', 64),
    ]);

    $laporan = $this->pemeriksa->periksa();

    expect($laporan['utuh'])->toBeFalse()
        ->and($laporan['pelanggaran'])->toContain('Kepala rantai tidak menunjuk baris terakhir log audit.');
});

it('menemukan jumlah baris pada kepala rantai yang tidak sesuai', function () {
    DB::table('rantai_audit')->where('id', 1)->update(['jumlah' => 99]);

    $laporan = $this->pemeriksa->periksa();

    expect($laporan['utuh'])->toBeFalse()
        ->and($laporan['pelanggaran'])->toContain('Kepala rantai menyebut 99 baris, tabel berisi 3.');
});

it('menemukan baris palsu yang disisipkan di ujung rantai', function () {
    $terakhir = LogAudit::query()->orderByDesc('id')->firstOrFail();

    DB::table('log_audit')->insert([
        'peristiwa' => PeristiwaAudit::PembayaranLunas->value,
        'pelaku_id' => null,
        'sasaran' => 'pesanan:LPK-PALSU',
        'rincian' => json_encode([], JSON_THROW_ON_ERROR),
        'id_permintaan' => null,
        'hash_sebelumnya' => $terakhir->hash,
        'hash' => str_repeat('b', 64),
        'dicatat_pada' => now()->format('Y-m-d H:i:s'),
    ]);

    $laporan = $this->pemeriksa->periksa();

    expect($laporan['utuh'])->toBeFalse()
        ->and($laporan['jumlah'])->toBe(4)
        ->and(implode(' ', $laporan['pelanggaran']))->toContain('tidak cocok dengan hash');
});

it('tidak memperbaiki apa pun saat memeriksa', function () {
    DB::table('log_audit')->where('id', $this->id[0])->update(['sasaran' => 'pesanan:DIUBAH']);
    $sebelum = DB::table('log_audit')->orderBy('id')->get();

    $this->pemeriksa->periksa();

    expect(DB::table('log_audit')->orderBy('id')->get())->toEqual($sebelum);
});

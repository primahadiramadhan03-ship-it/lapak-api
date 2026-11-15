<?php

declare(strict_types=1);

use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $pencatat = app(PencatatAudit::class);

    $this->admin = Pengguna::factory()->admin()->create(['nama' => 'Sari Admin']);
    $this->auditor = Pengguna::factory()->auditor()->create();

    foreach ([1, 2, 3] as $nomor) {
        $pencatat->catat(
            PeristiwaAudit::PembayaranLunas,
            $this->admin,
            "pesanan:LPK-{$nomor}",
            ['nomor' => $nomor],
        );
    }

    $pencatat->catat(PeristiwaAudit::TokoDisetujui, $this->admin, 'toko:7');

    // Nomor baris tidak selalu mulai dari 1: pada MySQL tabel yang sama dipakai lintas uji.
    $this->pertama = (int) LogAudit::query()->orderBy('id')->value('id');
});

it('menampilkan jejak audit terbaru lebih dulu kepada auditor', function () {
    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.peristiwa', 'toko.disetujui')
        ->assertJsonPath('data.0.pelaku.nama', 'Sari Admin')
        ->assertJsonPath('meta.total', 4)
        ->assertJsonPath('meta.jumlah_menurut_kepala', 4);
});

it('menyaring jejak audit menurut peristiwa, sasaran, dan pelaku', function () {
    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log?peristiwa=toko.disetujui')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sasaran', 'toko:7');

    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log?sasaran=pesanan:LPK-2')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sasaran', 'pesanan:LPK-2');

    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log?pelaku='.$this->admin->id)
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('menolak penyaringan yang tidak dikenal tanpa galat server', function () {
    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log?peristiwa=bukan.peristiwa')
        ->assertUnprocessable();

    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log?pelaku=abc')
        ->assertUnprocessable();
});

it('menampilkan satu baris beserta hasil hitung ulang hash-nya', function () {
    $log = LogAudit::query()->orderBy('id')->firstOrFail();

    $this->sebagai($this->auditor)
        ->getJson("/api/v1/audit/log/{$log->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $log->id)
        ->assertJsonPath('data.hash', $log->hash)
        ->assertJsonPath('data.tertaut', true);
});

it('menandai baris yang diubah lewat basis data sebagai tidak tertaut', function () {
    $log = LogAudit::query()->orderBy('id')->firstOrFail();

    DB::table('log_audit')->where('id', $log->id)->update([
        'sasaran' => 'pesanan:DIRUBAH',
    ]);

    $this->sebagai($this->auditor)
        ->getJson("/api/v1/audit/log/{$log->id}")
        ->assertOk()
        ->assertJsonPath('data.sasaran', 'pesanan:DIRUBAH')
        ->assertJsonPath('data.tertaut', false);
});

it('memberi 404 untuk baris yang tidak ada', function () {
    $this->sebagai($this->auditor)
        ->getJson('/api/v1/audit/log/999999')
        ->assertNotFound();
});

it('menolak peran yang tidak memegang izin audit.lihat', function (string $peran) {
    $pengguna = match ($peran) {
        'penjual' => Pengguna::factory()->penjual()->create(),
        'keuangan' => Pengguna::factory()->keuangan()->create(),
        'pembeli' => Pengguna::factory()->pembeli()->create(),
        'admin' => Pengguna::factory()->admin()->create(),
    };

    $this->sebagai($pengguna)
        ->getJson('/api/v1/audit/log')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
})->with(['penjual', 'keuangan', 'pembeli', 'admin']);

it('menolak sesi yang tidak segar meski izinnya ada', function () {
    $this->sebagai($this->auditor, segar: false)
        ->getJson('/api/v1/audit/log')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'LP-15');
});

it('menolak pembacaan tanpa sesi', function () {
    $this->tanpaKunci()
        ->getJson('/api/v1/audit/log')
        ->assertUnauthorized();
});

it('tidak menulis apa pun saat auditor membaca', function () {
    $sebelum = DB::table('log_audit')->count();
    $kepala = DB::table('rantai_audit')->where('id', 1)->first();

    $this->sebagai($this->auditor)->getJson('/api/v1/audit/log')->assertOk();
    $this->sebagai($this->auditor)->getJson("/api/v1/audit/log/{$this->pertama}")->assertOk();

    expect(DB::table('log_audit')->count())->toBe($sebelum)
        ->and(DB::table('rantai_audit')->where('id', 1)->first())->toEqual($kepala);
});

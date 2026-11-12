<?php

declare(strict_types=1);

use App\Domain\Audit\RantaiHash;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Services\PencatatAudit;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->pencatat = app(PencatatAudit::class);
    $this->admin = Pengguna::factory()->admin()->create();
});

it('menghasilkan hash yang dapat dihitung ulang dari baris yang tersimpan', function () {
    $this->pencatat->catat(PeristiwaAudit::PencairanDisetujui, $this->admin, 'pencairan:3', [
        'jumlah_rupiah' => 80_000,
        'pengaju' => 4,
        'catatan' => 'Sesuai mutasi — lunas',
    ]);

    $tersimpan = LogAudit::query()->sole();

    expect(RantaiHash::hitung($tersimpan->hash_sebelumnya, $tersimpan->isiTerlindung()))
        ->toBe($tersimpan->hash);
});

it('menyambung baris pertama ke kepala rantai yang kosong', function () {
    $log = $this->pencatat->catat(PeristiwaAudit::TokoDisetujui, $this->admin, 'toko:4');

    expect($log->hash_sebelumnya)->toBe(RantaiHash::awal())
        ->and($log->peristiwa)->toBe('toko.disetujui')
        ->and($log->pelaku_id)->toBe($this->admin->id)
        ->and($log->sasaran)->toBe('toko:4');
});

it('memindahkan kepala rantai ke baris terakhir dan menambah panjangnya', function () {
    $pertama = $this->pencatat->catat(PeristiwaAudit::PembayaranLunas, null, 'pembayaran:1');
    $kedua = $this->pencatat->catat(PeristiwaAudit::DanaDilepas, null, 'pesanan:LPK-1');

    $kepala = DB::table('rantai_audit')->where('id', 1)->first();

    expect($kedua->hash_sebelumnya)->toBe($pertama->hash)
        ->and($kepala->hash_terakhir)->toBe($kedua->hash)
        ->and((int) $kepala->jumlah)->toBe(2);
});

it('menolak rincian yang memuat rahasia, di tingkat mana pun', function (array $rincian) {
    expect(fn () => $this->pencatat->catat(PeristiwaAudit::MasukGagal, null, null, $rincian))
        ->toThrow(LogicException::class, 'tidak boleh memuat');

    expect(LogAudit::count())->toBe(0);
})->with([
    'kata sandi' => [['surel' => 'a@b.c', 'kata_sandi' => 'rahasia123']],
    'kunci sesi' => [['kunci' => 'lpk_...']],
    'bersarang' => [['permintaan' => ['badan' => ['tanda_tangan' => 'abc']]]],
]);

it('tidak dapat diubah maupun dihapus lewat model', function () {
    $log = $this->pencatat->catat(PeristiwaAudit::TokoDisetujui, $this->admin, 'toko:4');

    expect(fn () => $log->forceFill(['sasaran' => 'toko:5'])->save())
        ->toThrow(LogicException::class, 'LP-19');
    expect(fn () => $log->delete())->toThrow(LogicException::class, 'LP-19');

    expect(LogAudit::sole()->sasaran)->toBe('toko:4');
});

it('ikut batal bila transaksi tindakannya batal', function () {
    try {
        DB::transaction(function () {
            $this->pencatat->catat(PeristiwaAudit::DanaDilepas, null, 'pesanan:LPK-1');

            throw new RuntimeException('tindakan gagal setelah dicatat');
        });
    } catch (RuntimeException) {
    }

    $kepala = DB::table('rantai_audit')->where('id', 1)->first();

    expect(LogAudit::count())->toBe(0)
        ->and((int) $kepala->jumlah)->toBe(0)
        ->and($kepala->hash_terakhir)->toBe(RantaiHash::awal());
});

it('mencatat waktu tanpa pecahan detik dan memakai waktu itu pada hash', function () {
    $log = $this->pencatat->catat(PeristiwaAudit::StokDisesuaikan, $this->admin, 'produk:9');

    expect($log->dicatat_pada->format('u'))->toBe('000000')
        ->and($log->isiTerlindung()['dicatat_pada'])->toBe($log->dicatat_pada->format('Y-m-d H:i:s'));
});

it('menolak pencatatan bila kepala rantai tidak ada', function () {
    DB::table('rantai_audit')->delete();

    expect(fn () => $this->pencatat->catat(PeristiwaAudit::MasukBerhasil, $this->admin))
        ->toThrow(LogicException::class, 'Jalankan migrasi');

    expect(LogAudit::count())->toBe(0);
});

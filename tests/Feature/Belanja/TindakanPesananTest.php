<?php

declare(strict_types=1);

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;
use Database\Seeders\DompetSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->seed(DompetSeeder::class);

    $this->buku = app(BukuBesar::class);
    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->sri = Pengguna::factory()->penjual()->create();
    $this->batik = Toko::factory()->for($this->sri, 'pemilik')->create();
    $this->kain = Produk::factory()->for($this->batik)->create([
        'harga_rupiah' => PesananUji::HARGA,
        'stok' => 5,
    ]);

    $this->pesanan = PesananUji::dibayar($this->andi, $this->kain);
    $this->pesanan->forceFill([
        'status' => StatusPesanan::Dikirim,
        'dikirim_pada' => now()->subDay(),
        'nomor_resi' => 'JNE-00012345',
    ])->save();

    $this->alamat = "/api/v1/pesanan/{$this->pesanan->nomor}/terima";

    $this->saldo = function (string|JenisDompet $jenis): int {
        $kode = $jenis instanceof JenisDompet ? $jenis->value : $jenis;

        return (int) DB::table('dompet')->where('kode', $kode)->value('saldo_rupiah');
    };

    $this->saldoSri = function (): int {
        return $this->buku->dompetPenjual($this->sri)->saldo_rupiah;
    };

    $this->kirim = function (string $uri, array $badan = [], ?string $kunciIdempoten = null) {
        $req = $this;
        if ($kunciIdempoten !== null) {
            $req = $req->withHeader('Idempotency-Key', $kunciIdempoten);
        }

        return $req->postJson($uri, $badan);
    };
});

describe('konfirmasi terima', function () {
    it('menyelesaikan pesanan dan melepas dana kepada penjual (LP-12)', function () {
        $this->sebagai($this->andi);

        ($this->kirim)($this->alamat)
            ->assertOk()
            ->assertJsonPath('data.status', 'selesai')
            ->assertJsonPath('data.nomor', $this->pesanan->nomor);

        expect(($this->saldo)('escrow'))->toBe(0)
            ->and(($this->saldo)('pendapatan'))->toBe(18_500)
            ->and(($this->saldoSri)())->toBe(363_500)
            ->and($this->pesanan->refresh()->selesai_pada)->not->toBeNull();

        $jejak = LogAudit::query()->where('peristiwa', 'dana.dilepas')->sole();

        expect($jejak->pelaku_id)->toBe($this->andi->id)
            ->and($jejak->rincian['bagian_penjual_rupiah'])->toBe(363_500);
    });

    it('melepas dana satu kali walau tombolnya ditekan berulang (LP-11)', function () {
        $this->sebagai($this->andi);

        ($this->kirim)($this->alamat, [], 'terima-kain-000001')->assertOk();
        ($this->kirim)($this->alamat, [], 'terima-kain-000001')
            ->assertOk()
            ->assertHeader('Idempotency-Replayed', 'true');

        // Kunci berbeda: bukan putaran ulang. Yang menolak sekarang aturan status.
        ($this->kirim)($this->alamat, [], 'terima-kain-000002')
            ->assertConflict()
            ->assertJsonPath('galat.kode', 'LP-10');

        expect(($this->saldoSri)())->toBe(363_500)
            ->and(LogAudit::query()->where('peristiwa', 'dana.dilepas')->count())->toBe(1);
    });

    it('menjawab 404 untuk pesanan orang lain, dan menolak penjualnya sendiri', function () {
        $dewi = Pengguna::factory()->pembeli()->create();
        $this->sebagai($dewi);
        ($this->kirim)($this->alamat)->assertNotFound();

        // Bu Sri memegang peran pembeli dan penjual. Ia bukan pembeli pesanan ini: 404.
        // Penjual tidak pernah dapat melepas dana untuk dirinya sendiri.
        $this->sebagai($this->sri);
        ($this->kirim)($this->alamat)->assertNotFound();

        expect($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dikirim)
            ->and(($this->saldo)('escrow'))->toBe(382_000);
    });
});

describe('batal pesanan oleh pembeli', function () {
    it('membatalkan pesanan yang sudah dibayar: stok dan dana kembali', function () {
        $this->pesanan->forceFill([
            'status' => StatusPesanan::Dibayar,
            'dikirim_pada' => null,
            'nomor_resi' => null,
        ])->save();

        $this->sebagai($this->andi);

        ($this->kirim)("/api/v1/pesanan/{$this->pesanan->nomor}/batal", [
            'alasan' => 'Salah pilih warna',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'dibatalkan')
            ->assertJsonPath('data.alasan_batal', 'Salah pilih warna');

        expect($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
            ->and($this->kain->refresh()->stok)->toBe(7)
            ->and(($this->saldo)('escrow'))->toBe(0)
            ->and(($this->saldo)('eksternal'))->toBe(0)
            ->and(LogAudit::query()->whereIn('peristiwa', ['pesanan.dibatalkan', 'dana.dikembalikan'])->orderBy('id')->pluck('peristiwa')->all())
            ->toBe(['dana.dikembalikan', 'pesanan.dibatalkan']);
    });

    it('menolak pembatalan jika pesanan sudah dikirim (409 LP-10)', function () {
        $this->sebagai($this->andi);

        ($this->kirim)("/api/v1/pesanan/{$this->pesanan->nomor}/batal", [
            'alasan' => 'Mau ganti alamat',
        ])
            ->assertConflict()
            ->assertJsonPath('galat.kode', 'LP-10');

        expect($this->pesanan->refresh()->status)->toBe(StatusPesanan::Dikirim);
    });
});

describe('batal checkout oleh pembeli', function () {
    it('membatalkan checkout yang belum dibayar dan mengembalikan stoknya', function () {
        $belum = PesananUji::belumDibayar($this->andi, $this->kain);

        $this->sebagai($this->andi);

        ($this->kirim)("/api/v1/checkout/{$belum->checkout->kode}/batal", [
            'alasan' => 'Berubah pikiran sebelum bayar',
        ])
            ->assertOk()
            ->assertJsonPath('data.kode', $belum->checkout->kode);

        expect($belum->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
            ->and(LogAudit::query()->where('peristiwa', 'checkout.dibatalkan')->exists())->toBeTrue();
    });

    it('menolak pembatalan checkout jika sudah lunas atau ditutup (409 LP-10)', function () {
        $this->sebagai($this->andi);

        ($this->kirim)("/api/v1/checkout/{$this->pesanan->checkout->kode}/batal", [
            'alasan' => 'Batal dong',
        ])
            ->assertConflict()
            ->assertJsonPath('galat.kode', 'LP-10');
    });
});

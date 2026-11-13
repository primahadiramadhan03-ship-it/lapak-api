<?php

declare(strict_types=1);

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Exceptions\GalatDomain;
use App\Exceptions\TransisiPesananTidakSah;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\BukuBesar;
use App\Services\LayananPesanan;
use Database\Seeders\DompetSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    $this->seed(DompetSeeder::class);

    $this->layanan = app(LayananPesanan::class);
    $this->buku = app(BukuBesar::class);

    $this->andi = Pengguna::factory()->create();
    $this->sri = Pengguna::factory()->penjual()->create();
    $this->batik = Toko::factory()->for($this->sri, 'pemilik')->create();
    $this->kain = Produk::factory()->for($this->batik)->create([
        'harga_rupiah' => PesananUji::HARGA,
        'stok' => 3,
    ]);

    $this->pesanan = PesananUji::dibayar($this->andi, $this->kain, 2);

    $this->saldo = function (string $kode): int {
        return (int) DB::table('dompet')->where('kode', $kode)->value('saldo_rupiah');
    };

    $this->peristiwa = function (): array {
        return LogAudit::query()->orderBy('id')->pluck('peristiwa')->all();
    };
});

it('penjual mengemas pesanan: status dikemas dan cap waktu dikemas_pada terisi (LP-10)', function () {
    $kemas = $this->layanan->kemas($this->pesanan);

    expect($kemas->status)->toBe(StatusPesanan::Dikemas)
        ->and($kemas->dikemas_pada)->not->toBeNull();
});

it('penjual mengirim pesanan: status dikirim, cap waktu dikirim_pada dan nomor resi terisi (LP-10)', function () {
    $this->layanan->kemas($this->pesanan);
    $kirim = $this->layanan->kirim($this->pesanan, 'JNE-99887766');

    expect($kirim->status)->toBe(StatusPesanan::Dikirim)
        ->and($kirim->dikirim_pada)->not->toBeNull()
        ->and($kirim->nomor_resi)->toBe('JNE-99887766');
});

it('menyelesaikan pesanan dan melepas dananya dalam satu langkah oleh pembeli (LP-12)', function () {
    $this->layanan->kemas($this->pesanan);
    $this->layanan->kirim($this->pesanan, 'JNE-00012345');
    $pesanan = $this->pesanan;

    $selesai = $this->layanan->terima($pesanan, PelakuPesanan::Pembeli, $this->andi);

    expect($selesai->status)->toBe(StatusPesanan::Selesai)
        ->and($selesai->selesai_pada)->not->toBeNull()
        ->and(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)("penjual:{$this->sri->id}"))->toBe(363_500)
        ->and(($this->saldo)('pendapatan'))->toBe(18_500);

    expect(LogAudit::query()->where('peristiwa', 'pesanan.diterima')->sole())
        ->pelaku_id->toBe($this->andi->id)
        ->sasaran->toBe("pesanan:{$pesanan->nomor}")
        ->rincian->toMatchArray([
            'oleh' => 'pembeli',
        ]);

    expect(LogAudit::query()->where('peristiwa', 'dana.dilepas')->sole())
        ->pelaku_id->toBe($this->andi->id)
        ->sasaran->toBe("pesanan:{$pesanan->nomor}")
        ->rincian->toMatchArray([
            'bagian_penjual_rupiah' => 363_500,
            'komisi_rupiah' => 18_500,
        ]);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menyelesaikan pesanan secara otomatis oleh sistem setelah masa tahan lewat (LP-12)', function () {
    $this->layanan->kemas($this->pesanan);
    $this->layanan->kirim($this->pesanan, 'SICEPAT-123456');
    $pesanan = $this->pesanan;

    $selesai = $this->layanan->terima($pesanan, PelakuPesanan::Sistem);

    expect($selesai->status)->toBe(StatusPesanan::Selesai)
        ->and($selesai->selesai_pada)->not->toBeNull()
        ->and(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)("penjual:{$this->sri->id}"))->toBe(363_500)
        ->and(($this->saldo)('pendapatan'))->toBe(18_500);

    expect(LogAudit::query()->where('peristiwa', 'pesanan.selesai_otomatis')->sole())
        ->pelaku_id->toBeNull()
        ->sasaran->toBe("pesanan:{$pesanan->nomor}")
        ->rincian->toMatchArray([
            'oleh' => 'sistem',
        ]);

    expect(LogAudit::query()->where('peristiwa', 'dana.dilepas')->sole())
        ->pelaku_id->toBeNull()
        ->sasaran->toBe("pesanan:{$pesanan->nomor}");

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
});

it('menolak konfirmasi terima sebelum pesanan dikirim, tanpa memindahkan uang', function () {
    expect(fn () => $this->layanan->terima($this->pesanan, PelakuPesanan::Pembeli, $this->andi))
        ->toThrow(TransisiPesananTidakSah::class);

    expect(($this->saldo)('escrow'))->toBe(382_000)
        ->and(LogAudit::count())->toBe(0);
});

it('membatalkan pesanan yang sudah dibayar: stok kembali, dana kembali, dua jejak', function (
    PelakuPesanan $oleh,
) {
    $pelaku = $oleh === PelakuPesanan::Pembeli ? $this->andi : $this->sri;

    $batal = $this->layanan->batalkan($this->pesanan, $oleh, $pelaku, 'Salah pilih warna');

    expect($batal)
        ->status->toBe(StatusPesanan::Dibatalkan)
        ->dibatalkan_oleh->toBe($oleh->value)
        ->alasan_batal->toBe('Salah pilih warna')
        ->and($this->kain->refresh()->stok)->toBe(5)
        ->and(($this->saldo)('escrow'))->toBe(0)
        ->and(($this->saldo)('eksternal'))->toBe(0)
        ->and(($this->peristiwa)())->toBe(['pesanan.dibatalkan', 'dana.dikembalikan']);

    $this->artisan('lapak:periksa-buku')->assertSuccessful();
})->with([PelakuPesanan::Pembeli, PelakuPesanan::Penjual]);

it('menolak pembatalan per-pesanan yang belum dibayar dengan 409 LP-10', function () {
    $pesananBelumBayar = PesananUji::belumDibayar($this->andi, $this->kain, 2);

    expect(fn () => $this->layanan->batalkan($pesananBelumBayar, PelakuPesanan::Pembeli, $this->andi, 'Ingin batal'))
        ->toThrow(function (GalatDomain $e) {
            expect($e->kode)->toBe('LP-10')
                ->and($e->statusHttp)->toBe(409)
                ->and($e->getMessage())->toBe('Pesanan yang belum dibayar dibatalkan bersama checkout-nya.');
        });
});

it('menolak pembatalan pesanan yang sudah dikirim (LP-10)', function () {
    $this->layanan->kemas($this->pesanan);
    $this->layanan->kirim($this->pesanan, 'JNE-12345678');

    expect(fn () => $this->layanan->batalkan($this->pesanan, PelakuPesanan::Pembeli, $this->andi, 'Batal paket sudah di jalan'))
        ->toThrow(TransisiPesananTidakSah::class);
});

it('membatalkan checkout yang belum dibayar oleh pembeli: stok kembali dan tagihan dibatalkan (LP-10)', function () {
    $pesanan = PesananUji::belumDibayar($this->andi, $this->kain, 2);
    $checkout = $pesanan->checkout;
    $pembayaran = $checkout->pembayaran;

    $stokAwal = $this->kain->refresh()->stok; // 3

    $sukses = $this->layanan->batalkanCheckout($checkout->id, PelakuPesanan::Pembeli, $this->andi, 'Batal sebelum bayar');

    expect($sukses)->toBeTrue()
        ->and($pesanan->refresh()->status)->toBe(StatusPesanan::Dibatalkan)
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Dibatalkan)
        ->and($this->kain->refresh()->stok)->toBe($stokAwal + 2)
        ->and(LogAudit::query()->where('peristiwa', 'checkout.dibatalkan')->sole())
        ->pelaku_id->toBe($this->andi->id)
        ->sasaran->toBe("checkout:{$checkout->kode}");

    // Pembatalan ulang mengembalikan false karena tagihan sudah ditutup
    expect($this->layanan->batalkanCheckout($checkout->id, PelakuPesanan::Pembeli, $this->andi, 'Batal lagi'))
        ->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Enums\StatusPembayaran;
use App\Exceptions\GalatDomain;
use App\Http\Resources\PembayaranResource;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Services\GembokCheckout;
use App\Services\LayananPembayaran;
use Database\Seeders\DompetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->layanan = app(LayananPembayaran::class);
    $this->gembok = app(GembokCheckout::class);
});

it('membuat tagihan pembayaran sebesar total checkout (LP-7)', function () {
    $checkout = Checkout::factory()->create(['total_rupiah' => 250_000]);

    $pembayaran = $this->layanan->buat($checkout);

    expect($pembayaran->checkout_id)->toBe($checkout->id)
        ->and($pembayaran->jumlah_rupiah)->toBe(250_000)
        ->and($pembayaran->status)->toBe(StatusPembayaran::Menunggu)
        ->and($pembayaran->kode)->toStartWith('BYR-')
        ->and($pembayaran->batas_bayar_pada->greaterThan(now()))->toBeTrue();

    $this->assertDatabaseHas('pembayaran', [
        'id' => $pembayaran->id,
        'checkout_id' => $checkout->id,
        'jumlah_rupiah' => 250_000,
        'status' => StatusPembayaran::Menunggu->value,
    ]);
});

it('menolak tagihan ganda untuk satu checkout yang sama (LP-7)', function () {
    $checkout = Checkout::factory()->create();

    $this->layanan->buat($checkout);

    // Pembuatan kedua harus gagal karena checkout_id unik
    expect(fn () => $this->layanan->buat($checkout))->toThrow(QueryException::class);
});

it('mengambil pembayaran milik sebuah checkout', function () {
    $checkout = Checkout::factory()->create();
    $dibuat = $this->layanan->buat($checkout);

    $diambil = $this->layanan->untuk($checkout);

    expect($diambil->id)->toBe($dibuat->id)
        ->and($diambil->kode)->toBe($dibuat->kode);
});

it('tidak menandai kedaluwarsa bila pembayaran belum lewat batas waktu', function () {
    $checkout = Checkout::factory()->create();
    $pembayaran = Pembayaran::factory()->for($checkout)->create([
        'status' => StatusPembayaran::Menunggu,
        'batas_bayar_pada' => now()->addHours(12),
    ]);

    $hasil = $this->gembok->dalam($checkout->id, fn () => $this->layanan->tandaiKedaluwarsa($checkout));

    expect($hasil)->toBeFalse()
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Menunggu);
});

it('menandai kedaluwarsa pembayaran yang telah lewat batas waktu di dalam gembok checkout', function () {
    $checkout = Checkout::factory()->create();
    $pembayaran = Pembayaran::factory()->for($checkout)->create([
        'status' => StatusPembayaran::Menunggu,
        'batas_bayar_pada' => now()->subMinute(),
    ]);

    $hasil = $this->gembok->dalam($checkout->id, fn () => $this->layanan->tandaiKedaluwarsa($checkout));

    expect($hasil)->toBeTrue()
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Kedaluwarsa);
});

it('tidak menandai kedaluwarsa bila status pembayaran bukan menunggu (sudah lunas)', function () {
    $checkout = Checkout::factory()->create();
    $pembayaran = Pembayaran::factory()->lunas()->for($checkout)->create([
        'batas_bayar_pada' => now()->subHour(),
    ]);

    $hasil = $this->gembok->dalam($checkout->id, fn () => $this->layanan->tandaiKedaluwarsa($checkout));

    expect($hasil)->toBeFalse()
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Lunas);
});

it('scopeLewatBatas hanya mengambil pembayaran berstatus menunggu yang melewati batas waktu', function () {
    $kedaluwarsa = Pembayaran::factory()->create([
        'status' => StatusPembayaran::Menunggu,
        'batas_bayar_pada' => now()->subMinutes(10),
    ]);

    $masihBerlaku = Pembayaran::factory()->create([
        'status' => StatusPembayaran::Menunggu,
        'batas_bayar_pada' => now()->addHours(1),
    ]);

    $sudahLunas = Pembayaran::factory()->lunas()->create([
        'batas_bayar_pada' => now()->subMinutes(10),
    ]);

    $hasil = Pembayaran::query()->lewatBatas()->pluck('id');

    expect($hasil)->toContain($kedaluwarsa->id)
        ->and($hasil)->not->toContain($masihBerlaku->id)
        ->and($hasil)->not->toContain($sudahLunas->id);
});

it('pembayaran resource mengembalikan format yang sesuai kontrak', function () {
    $pembayaran = Pembayaran::factory()->create([
        'jumlah_rupiah' => 125_000,
        'status' => StatusPembayaran::Menunggu,
    ]);

    $resource = (new PembayaranResource($pembayaran))->toArray(Request::create('/'));

    expect($resource)->toMatchArray([
        'kode' => $pembayaran->kode,
        'status' => 'menunggu',
        'jumlah' => [
            'rupiah' => 125_000,
            'tampilan' => 'Rp 125.000',
        ],
    ])
        ->and($resource)->not->toHaveKey('id')
        ->and($resource)->not->toHaveKey('checkout_id')
        ->and($resource)->not->toHaveKey('id_peristiwa');
});

it('menandai dibatalkan untuk pembayaran yang masih menunggu di dalam gembok checkout', function () {
    $checkout = Checkout::factory()->create();
    $pembayaran = Pembayaran::factory()->for($checkout)->create([
        'status' => StatusPembayaran::Menunggu,
        'batas_bayar_pada' => now()->addHours(12),
    ]);

    $hasil = $this->gembok->dalam($checkout->id, fn () => $this->layanan->tandaiDibatalkan($checkout));

    expect($hasil)->toBeTrue()
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Dibatalkan);
});

it('tidak menandai dibatalkan bila status pembayaran bukan menunggu', function (string $state) {
    $checkout = Checkout::factory()->create();
    $pembayaran = Pembayaran::factory()->{$state}()->for($checkout)->create();

    $hasil = $this->gembok->dalam($checkout->id, fn () => $this->layanan->tandaiDibatalkan($checkout));

    expect($hasil)->toBeFalse()
        ->and($pembayaran->refresh()->status)->not->toBe(StatusPembayaran::Dibatalkan);
})->with(['lunas', 'kedaluwarsa']);

it('mengembalikan pembayaran yang masuk bila tagihan sudah dibatalkan (LP-13)', function () {
    $this->seed(DompetSeeder::class);

    $checkout = Checkout::factory()->create(['total_rupiah' => 150_000]);
    $pembayaran = Pembayaran::factory()->dibatalkan()->for($checkout)->create(['jumlah_rupiah' => 150_000]);

    $hasil = $this->layanan->terima($pembayaran->kode, 150_000, 'evt_batal_01');

    expect($hasil)->toBe('dikembalikan')
        ->and($pembayaran->refresh()->status)->toBe(StatusPembayaran::Dikembalikan)
        ->and($pembayaran->lunas_pada)->toBeNull();
});

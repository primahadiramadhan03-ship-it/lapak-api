<?php

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\PembatalanOtomatis;

function checkoutMenunggu(array $tagihanAttr = [], int $stok = 3, int $kuantitas = 2): array
{
    $produk = Produk::factory()->create(['stok' => $stok]);
    $checkout = Checkout::factory()->create();
    $tagihan = Pembayaran::factory()->for($checkout)->create([
        'status' => StatusPembayaran::Menunggu,
        'batas_bayar_pada' => now()->subMinute(),
        ...$tagihanAttr,
    ]);
    $pesanan = Pesanan::factory()->for($checkout)->create([
        'status' => StatusPesanan::MenungguPembayaran,
    ]);
    ButirPesanan::factory()->for($pesanan)->create([
        'produk_id' => $produk->id,
        'kuantitas' => $kuantitas,
    ]);

    return [$checkout, $tagihan, [$produk]];
}

it('aman dijalankan berulang: stok tidak dikembalikan dua kali', function () {
    [, , $produk] = checkoutMenunggu(['batas_bayar_pada' => now()->subMinute()]);

    $this->artisan('lapak:batalkan-kedaluwarsa')->assertSuccessful();
    $this->artisan('lapak:batalkan-kedaluwarsa')
        ->expectsOutputToContain('0 checkout dibatalkan')
        ->assertSuccessful();

    expect($produk[0]->refresh()->stok)->toBe(5);
});

it('memeriksa ulang tagihan di bawah gembok sebelum membatalkan', function () {
    [$checkout, $tagihan, $produk] = checkoutMenunggu(['batas_bayar_pada' => now()->subMinute()]);

    // Penjadwal sudah memilih checkout ini, tetapi pembayarannya masuk sesaat sebelum gembok.
    $tagihan->forceFill(['status' => StatusPembayaran::Lunas])->save();

    expect(app(PembatalanOtomatis::class)->batalkan($checkout->id))->toBeFalse()
        ->and($checkout->pesanan()->pluck('status')->unique()->all())
        ->toBe([StatusPesanan::MenungguPembayaran])
        ->and($produk[0]->refresh()->stok)->toBe(3);
});

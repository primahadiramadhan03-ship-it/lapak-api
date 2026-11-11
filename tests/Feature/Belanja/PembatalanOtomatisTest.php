<?php

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\StatusPembayaran;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\PembatalanOtomatis;
use Illuminate\Support\Facades\DB;

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

it('mengembalikan stok urut nomor produk, apa pun urutan butirnya', function () {
    $checkout = Checkout::factory()->create();
    Pembayaran::factory()->for($checkout)->create(['batas_bayar_pada' => now()->subMinute()]);
    $pesanan = Pesanan::factory()->for($checkout)->create();
    [$kecil, $tengah, $besar] = Produk::factory()->count(3)->create(['stok' => 0])->all();

    // Butir sengaja disimpan dari nomor produk terbesar.
    foreach ([$besar, $kecil, $tengah] as $barang) {
        ButirPesanan::factory()->for($pesanan)->create(['produk_id' => $barang->id]);
    }

    DB::enableQueryLog();
    app(PembatalanOtomatis::class)->batalkan($checkout->id);

    $urutan = collect(DB::getQueryLog())
        // Tanda kutip nama tabel berbeda antara SQLite dan MySQL, maka dicocokkan dengan titik.
        ->filter(fn (array $kueri) => preg_match('/^update .produk. /', $kueri['query']) === 1)
        ->map(fn (array $kueri) => last($kueri['bindings']))
        ->values()
        ->all();

    expect($urutan)->toBe([$kecil->id, $tengah->id, $besar->id]);
});

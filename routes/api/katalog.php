<?php

declare(strict_types=1);

use App\Http\Controllers\KatalogController;
use App\Http\Controllers\TokoSayaController;
use Illuminate\Support\Facades\Route;

Route::get('produk', [KatalogController::class, 'daftarProduk'])
    ->name('produk.index');

Route::get('produk/{slug:slug}', [KatalogController::class, 'detailProduk'])
    ->name('produk.show');

Route::get('toko/{slug:slug}', [KatalogController::class, 'detailToko'])
    ->name('toko.show');

Route::get('kategori', [KatalogController::class, 'daftarKategori'])
    ->name('kategori.index');

// Toko dan produk milik pengguna yang sedang masuk.
Route::middleware('auth:sesi')->group(function (): void {
    Route::get('toko-saya', [TokoSayaController::class, 'show'])->name('toko-saya.show');
});

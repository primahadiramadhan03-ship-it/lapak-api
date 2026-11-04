<?php

declare(strict_types=1);

use App\Http\Controllers\KatalogController;
use App\Http\Controllers\ProdukSayaController;
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
// Alamatnya tidak memuat pengenal toko: toko selalu ditentukan sesi.
Route::middleware('auth:sesi')
    ->prefix('toko-saya')
    ->name('toko-saya.')
    ->whereNumber('id')
    ->group(function (): void {
        Route::get('/', [TokoSayaController::class, 'show'])->name('show');
        Route::post('/', [TokoSayaController::class, 'store'])->name('store');
        Route::patch('/', [TokoSayaController::class, 'update'])->name('update');

        Route::get('produk', [ProdukSayaController::class, 'index'])->name('produk.index');
        Route::post('produk', [ProdukSayaController::class, 'store'])->name('produk.store');
        Route::patch('produk/{id}', [ProdukSayaController::class, 'update'])->name('produk.update');
        Route::delete('produk/{id}', [ProdukSayaController::class, 'destroy'])->name('produk.destroy');
    });

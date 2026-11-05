<?php

declare(strict_types=1);

use App\Http\Controllers\DompetController;
use App\Http\Controllers\RiwayatMutasiController;
use Illuminate\Support\Facades\Route;

// Pintu HTTP pertama ke domain uang, dan seluruhnya di belakang kunci sesi.
Route::middleware('auth:sesi')->group(function (): void {
    Route::get('dompet', [DompetController::class, 'show'])->name('dompet.show');
    // Endpoint sensitif memerlukan sesi segar (LP-15)
    Route::middleware('sesi.segar')->group(function (): void {
        Route::get('dompet/mutasi', [RiwayatMutasiController::class, 'index'])->name('dompet.mutasi');
    });
});

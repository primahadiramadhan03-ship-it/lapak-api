<?php

declare(strict_types=1);

use App\Http\Controllers\Dompet\SaldoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sesi')->group(function () {
    Route::get('/dompet/saya', SaldoController::class)->name('dompet.saya');
});

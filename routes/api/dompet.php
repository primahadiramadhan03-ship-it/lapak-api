<?php

declare(strict_types=1);

use App\Http\Controllers\DompetController;
use Illuminate\Support\Facades\Route;

// Pintu HTTP pertama ke domain uang, dan seluruhnya di belakang kunci sesi.
Route::middleware(['auth:sesi', 'tanpa-cache'])->group(function (): void {
    Route::get('dompet', [DompetController::class, 'show'])->name('dompet.show');
});

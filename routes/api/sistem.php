<?php

declare(strict_types=1);

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\KesehatanController;
use Illuminate\Support\Facades\Route;

Route::get('health', KesehatanController::class)->name('kesehatan');

// Log audit: hanya-baca, hanya pemegang izin audit.lihat (auditor), selalu dengan sesi segar.
// Tidak ada rute tulis di sini: satu-satunya penulis log audit adalah PencatatAudit (LP-19).
Route::middleware(['auth:sesi', 'izin:audit.lihat', 'sesi.segar'])
    ->prefix('audit')
    ->name('audit.')
    ->group(function (): void {
        Route::get('log', [AuditLogController::class, 'index'])->name('log.index');
        Route::get('log/{id}', [AuditLogController::class, 'show'])->name('log.show');
    });

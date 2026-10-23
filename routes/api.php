
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('/tes', function () {
        return response()->json([
            'pesan' => 'API Lapak berhasil berjalan',
        ]);
    })->name('tes');

    foreach (glob(__DIR__.'/api/*.php') ?: [] as $berkas) {
        require $berkas;
    }
});

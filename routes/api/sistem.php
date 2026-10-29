<?php

declare(strict_types=1);

use App\Http\Controllers\KesehatanController;
use Illuminate\Support\Facades\Route;

Route::get('health', KesehatanController::class)->name('kesehatan');

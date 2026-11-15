<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sesi yang sudah lama mati dibuang setiap dini hari.
Schedule::command('lapak:pangkas-sesi')->dailyAt('03:15');

// LP-10: checkout yang lewat batas bayar dibatalkan dan stoknya dikembalikan.
Schedule::command('lapak:batalkan-kedaluwarsa')->everyTenMinutes()->withoutOverlapping();

// LP-12: pesanan yang dikirim lebih dari masa tahan diselesaikan dan dananya dilepas.
Schedule::command('lapak:selesaikan-otomatis')->hourly()->withoutOverlapping();

// LP-11: rekaman kunci idempotensi dibuang setelah masa simpannya lewat.
Schedule::command('lapak:pangkas-idempotensi')->dailyAt('03:45')->withoutOverlapping();

// LP-19: rantai audit diperiksa setiap hari, bukan hanya saat rilis. Rantai yang terputus
// harus ketahuan dalam hitungan jam, bukan saat auditor membukanya berminggu-minggu kemudian.
Schedule::command('lapak:verifikasi-audit')->dailyAt('04:10')->withoutOverlapping();

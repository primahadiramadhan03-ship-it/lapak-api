<?php

namespace App\Providers;

use App\Models\Pengguna;
use App\Models\Sesi;
use App\Services\PemeriksaSesi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Di luar produksi, Eloquent menolak lazy loading, atribut yang belum dimuat,
        // dan pengisian kolom di luar $fillable.
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->daftarkanGuardSesi();
        $this->daftarkanPembatasLaju();
    }

    /**
     * Guard "sesi": membaca kunci dari header Authorization: Bearer,
     * menanyakannya ke PemeriksaSesi, lalu menitipkan sesi pada permintaan.
     */
    private function daftarkanGuardSesi(): void
    {
        Auth::viaRequest('sesi', function (Request $request): ?Pengguna {
            $sesi = app(PemeriksaSesi::class)->periksa($request->bearerToken());

            if ($sesi === null) {
                return null;
            }

            $request->attributes->set('sesi', $sesi);

            // Yang masuk log adalah pengenal sesi. Kunci utuh tidak pernah ditulis ke log.
            Log::withContext(['sesi' => $sesi->pengenal, 'pengguna' => $sesi->pengguna_id]);

            return $sesi->pengguna;
        });
    }

    /**
     * Pembatas laju percobaan masuk, konfirmasi kata sandi,
     * dan pintu transaksi.
     */
    private function daftarkanPembatasLaju(): void
    {
        RateLimiter::for('masuk', fn (Request $request) => [
            Limit::perMinute(config('lapak.laju.masuk_per_akun'))
                ->by('masuk:'.mb_strtolower((string) $request->input('surel')).'|'.$request->ip()),
            Limit::perMinute(config('lapak.laju.masuk_per_ip'))
                ->by('masuk-ip:'.$request->ip()),
        ]);

        RateLimiter::for('konfirmasi', fn (Request $request) => Limit::perMinute(
            config('lapak.laju.konfirmasi'),
        )->by('konfirmasi:'.($request->user('sesi')?->id ?? $request->ip())));

        // LP-15: pembatas transaksi menggunakan pengenal sesi, bukan alamat IP.
        RateLimiter::for('transaksi', fn (Request $request) => Limit::perMinute(
            config('lapak.transaksi.laju_per_menit'),
        )->by('transaksi:'.Sesi::dariPermintaan($request)->pengenal));
    }
}

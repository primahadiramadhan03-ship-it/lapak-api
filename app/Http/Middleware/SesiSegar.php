<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\SesiTidakSegar;
use App\Models\Sesi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan sesi yang sedang dipakai cukup segar untuk operasi sensitif (LP-15).
 * Pengguna harus mengkonfirmasi kata sandi dalam rentang waktu tertentu.
 * Hanya digunakan di belakang middleware auth:sesi.
 */
final class SesiSegar
{
    public function handle(Request $request, Closure $next): Response
    {
        $sesi = Sesi::dariPermintaan($request);

        if (! $sesi->segar()) {
            throw SesiTidakSegar::buat();
        }

        return $next($request);
    }
}

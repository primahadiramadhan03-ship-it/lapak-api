<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\NamaIzin;
use App\Models\Pengguna;
use App\Services\PemeriksaIzin;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga rute dengan parameter izin, misalnya izin:keranjang.kelola.
 * Dipakai sesudah auth:sesi; pengguna tanpa izin ditolak dengan kode DITOLAK.
 */
final class PastikanIzin
{
    public function __construct(private readonly PemeriksaIzin $pemeriksa) {}

    public function handle(Request $request, Closure $next, string $namaIzin): Response
    {
        $izin = NamaIzin::tryFrom($namaIzin);
        $pengguna = $request->user();

        if ($izin === null || ! $pengguna instanceof Pengguna || ! $this->pemeriksa->punya($pengguna, $izin)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}

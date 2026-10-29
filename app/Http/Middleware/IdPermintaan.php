<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdPermintaan
{
    /**
     * Membuat header X-Request-Id unik (UUID) untuk setiap respons dan mencatatnya ke log.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idPermintaan = (string) Str::uuid();

        $request->headers->set('X-Request-Id', $idPermintaan);
        Log::withContext(['request_id' => $idPermintaan]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $idPermintaan);

        return $response;
    }
}

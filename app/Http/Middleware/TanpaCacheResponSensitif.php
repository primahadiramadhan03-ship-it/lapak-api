<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TanpaCacheResponSensitif
{
    private const SENSITIVE_PATHS = [
        'api/v1/sesi',
        'api/v1/dompet',
        'api/v1/keranjang',
        'api/v1/masuk',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->adalahSensitif($request->path())) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        }

        return $response;
    }

    private function adalahSensitif(string $path): bool
    {
        foreach (self::SENSITIVE_PATHS as $sensitive) {
            if (str_starts_with($path, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}

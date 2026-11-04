<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class PenanganGalatApi
{
    /**
     * Satu pintu perender seluruh galat API menjadi format JSON yang seragam.
     */
    public static function tangani(Throwable $e, Request $request): ?JsonResponse
    {
        // Tangani jika permintaan mengarah ke API atau mengharapkan JSON
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $response = self::buatResponsGalat($e);

        // Pastikan header X-Request-Id selalu hadir pada setiap respons galat
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private static function buatResponsGalat(Throwable $e): JsonResponse
    {
        if ($e instanceof GalatDomain) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => $e->kode,
                    'pesan' => $e->getMessage(),
                    'rincian' => $e->rincian === [] ? null : $e->rincian,
                ],
            ], $e->statusHttp);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'VALIDASI',
                    'pesan' => 'Data kiriman tidak memenuhi aturan validasi.',
                    'rincian' => $e->errors(),
                ],
            ], 422);
        }

        if ($e instanceof AuthenticationException) {
            $response = response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'BELUM_MASUK',
                    'pesan' => 'Sesi tidak ada atau tidak berlaku.',
                    'rincian' => null,
                ],
            ], 401);

            // RFC 6750: header WWW-Authenticate untuk Bearer token
            $response->headers->set('WWW-Authenticate', 'Bearer realm="Lapak API"');

            return $response;
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'DITOLAK',
                    'pesan' => 'Pengguna tidak berwenang.',
                    'rincian' => null,
                ],
            ], 403);
        }

        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'TIDAK_DITEMUKAN',
                    'pesan' => 'Sumber daya tidak ditemukan.',
                    'rincian' => null,
                ],
            ], 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'METODE_TIDAK_DIIZINKAN',
                    'pesan' => 'Metode HTTP salah.',
                    'rincian' => null,
                ],
            ], 405);
        }

        if ($e instanceof ThrottleRequestsException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'TERLALU_SERING',
                    'pesan' => 'Melebihi batas laju permintaan.',
                    'rincian' => null,
                ],
            ], 429);
        }

        $statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        if ($statusCode === 429) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'TERLALU_SERING',
                    'pesan' => 'Melebihi batas laju permintaan.',
                    'rincian' => null,
                ],
            ], 429);
        }

        if ($statusCode === 503) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'TIDAK_SEHAT',
                    'pesan' => 'Basis data tidak terjangkau.',
                    'rincian' => null,
                ],
            ], 503);
        }

        return response()->json([
            'sukses' => false,
            'galat' => [
                'kode' => 'GALAT_SERVER',
                'pesan' => 'Galat tak terduga.',
                'rincian' => null,
            ],
        ], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500);
    }
}

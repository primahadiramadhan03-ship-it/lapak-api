<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpExceptionInterface;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    public function register(): void
    {
        //
    }

    public function render($request, Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'BELUM_MASUK',
                    'pesan' => 'Anda belum masuk atau sesi tidak berlaku.',
                    'rincian' => null,
                ],
            ], 401);
        }

        if ($e instanceof GalatDomain) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => $e->kode,
                    'pesan' => $e->getMessage(),
                    'rincian' => $e->rincian,
                ],
            ], $e->statusHttp);
        }

        if ($e instanceof ValidationException) {
            return response()->json([
                'sukses' => false,
                'galat' => [
                    'kode' => 'VALIDASI',
                    'pesan' => 'Data yang dikirim tidak memenuhi syarat.',
                    'rincian' => $e->errors(),
                ],
            ], $e->status);
        }

        return parent::render($request, $e);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function sukses(mixed $data, int $kode = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => null,
        ], $kode);
    }
}

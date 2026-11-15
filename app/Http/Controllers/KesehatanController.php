<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\GalatDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class KesehatanController extends Controller
{
    /**
     * Memeriksa kesehatan sistem dan ketersediaan basis data.
     */
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            throw new GalatDomain(
                kode: 'TIDAK_SEHAT',
                pesan: 'Basis data tidak terjangkau.',
                statusHttp: 503,
            );
        }

        return $this->sukses([
            'status' => 'sehat',
            'basis_data' => 'terhubung',
            'versi' => config('lapak.versi'),
        ]);
    }
}

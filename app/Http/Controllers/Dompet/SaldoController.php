<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dompet;

use App\Http\Controllers\Controller;
use App\Http\Resources\DompetResource;
use Illuminate\Http\Request;

final class SaldoController extends Controller
{
    public function __invoke(Request $request): DompetResource
    {
        $dompet = $request->user()->dompet;

        return new DompetResource($dompet);
    }
}

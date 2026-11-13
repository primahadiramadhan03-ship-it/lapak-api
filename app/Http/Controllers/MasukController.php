<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PeristiwaAudit;
use App\Enums\StatusAkun;
use App\Exceptions\GalatDomain;
use App\Exceptions\KredensialSalah;
use App\Http\Requests\MasukRequest;
use App\Http\Resources\PenggunaResource;
use App\Http\Resources\SesiResource;
use App\Models\Pengguna;
use App\Services\PemeriksaKataSandi;
use App\Services\PencatatAudit;
use App\Services\PenerbitSesi;
use Illuminate\Http\JsonResponse;

final class MasukController extends Controller
{
    public function __construct(
        private readonly PemeriksaKataSandi $sandi,
        private readonly PenerbitSesi $penerbit,
        private readonly PencatatAudit $audit,
    ) {}

    public function __invoke(MasukRequest $request): JsonResponse
    {
        $pengguna = Pengguna::query()->where('surel', $request->validated('surel'))->first();

        if (! $this->sandi->cocok($pengguna, $request->validated('kata_sandi'))) {
            // LP-19: kata sandi tidak boleh dicatat ke log audit
            $this->audit->catat(
                PeristiwaAudit::MasukGagal,
                null,
                null,
                ['surel' => $request->validated('surel')],
            );

            throw KredensialSalah::saatMasuk();
        }

        // Diperiksa SETELAH kata sandi terbukti benar. Orang yang tidak tahu kata sandinya
        // tidak boleh dapat mengetahui bahwa sebuah akun sedang ditangguhkan.
        if ($pengguna->status !== StatusAkun::Aktif) {
            throw new GalatDomain('AKUN_DITANGGUHKAN', 'Akun ini ditangguhkan. Hubungi admin.', 403);
        }

        [$sesi, $kunci] = $this->penerbit->terbitkan(
            $pengguna, $request->namaPerangkat(), $request->ip(), $request->userAgent(),
        );

        $this->audit->catat(
            PeristiwaAudit::MasukBerhasil,
            $pengguna,
            "sesi:{$sesi->pengenal}",
            [
                'nama_perangkat' => $request->namaPerangkat(),
                'ip' => $request->ip(),
            ],
        );

        return $this->sukses([
            // Satu-satunya saat kunci utuh keluar dari server. Klien wajib menyimpannya sekarang.
            'kunci' => $kunci,
            'sesi' => SesiResource::saatIni($sesi),
            'pengguna' => PenggunaResource::make($pengguna),
        ], 201);
    }
}

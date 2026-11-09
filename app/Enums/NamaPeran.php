<?php

declare(strict_types=1);

namespace App\Enums;

enum NamaPeran: string
{
    case Pembeli = 'pembeli';
    case Penjual = 'penjual';
    case Admin = 'admin';
    case Keuangan = 'keuangan';
    case Auditor = 'auditor';

    /**
     * Matriks izin yang disepakati pada Sesi 3.
     * Inilah satu-satunya sumber kebenaran; seeder dan command membaca dari sini.
     *
     * @return list<NamaIzin>
     */
    public function izinBawaan(): array
    {
        return match ($this) {
            self::Pembeli => [
                NamaIzin::KeranjangKelola,
                NamaIzin::TokoBuka,
                NamaIzin::PesananBuat,
            ],
            self::Penjual => [
                NamaIzin::ProdukKelola,
                NamaIzin::DompetLihat,
            ],
            self::Admin => [
                NamaIzin::TokoModerasi,
                NamaIzin::PeranKelola,
            ],
            self::Keuangan, self::Auditor => [
                NamaIzin::BukuBesarLihat,
            ],
        };
    }
}

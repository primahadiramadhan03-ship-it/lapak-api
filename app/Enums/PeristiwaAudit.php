<?php

declare(strict_types=1);

namespace App\Enums;

enum PeristiwaAudit: string
{
    case MasukBerhasil = 'masuk.berhasil';
    case MasukGagal = 'masuk.gagal';
    case SesiDicabut = 'sesi.dicabut';
    case KunciLamaDipakai = 'sesi.kunci_lama_dipakai';
    case PeranDiberikan = 'peran.diberikan';
    case PeranDicabut = 'peran.dicabut';

    case TokoDisetujui = 'toko.disetujui';
    case TokoDitangguhkan = 'toko.ditangguhkan';
    case StokDisesuaikan = 'stok.disesuaikan';

    case CheckoutDibatalkan = 'checkout.dibatalkan';
    case PesananDibatalkan = 'pesanan.dibatalkan';
    case PesananDiterima = 'pesanan.diterima';
    case PesananSelesaiOtomatis = 'pesanan.selesai_otomatis';

    case PembayaranLunas = 'pembayaran.lunas';
    case PembayaranDikembalikan = 'pembayaran.dikembalikan';
    case DanaDilepas = 'dana.dilepas';
    case DanaDikembalikan = 'dana.dikembalikan';
    case PencairanDiajukan = 'pencairan.diajukan';
    case PencairanDisetujui = 'pencairan.disetujui';
    case PencairanDitolak = 'pencairan.ditolak';
}

<?php

declare(strict_types=1);

namespace App\Enums;

enum NamaIzin: string
{
    case KeranjangKelola = 'keranjang.kelola';
    case TokoBuka = 'toko.buka';
    case PesananBuat = 'pesanan.buat';
    case PesananProses = 'pesanan.proses';
    case ProdukKelola = 'produk.kelola';
    case DompetLihat = 'dompet.lihat';
    case TokoModerasi = 'toko.moderasi';
    case PeranKelola = 'peran.kelola';
    case BukuBesarLihat = 'buku-besar.lihat';
    case PencairanAjukan = 'pencairan.ajukan';
    case PencairanSetujui = 'pencairan.setujui';

    public function keterangan(): string
    {
        return match ($this) {
            self::KeranjangKelola => 'Mengisi dan mengubah keranjang belanja sendiri',
            self::TokoBuka => 'Mengajukan pembukaan toko',
            self::PesananBuat => 'Mengelola alamat kirim, checkout, dan melihat pesanan sendiri',
            self::PesananProses => 'Memproses pesanan toko sendiri: mengemas, mengirim, dan membatalkan',
            self::ProdukKelola => 'Menambah, mengubah, dan mengarsipkan produk toko sendiri',
            self::DompetLihat => 'Melihat saldo dan mutasi dompet sendiri',
            self::TokoModerasi => 'Menyetujui dan menangguhkan toko',
            self::PeranKelola => 'Memberi dan mencabut peran pengguna',
            self::BukuBesarLihat => 'Membaca seluruh dompet dan mutasi marketplace',
            self::PencairanAjukan => 'Mengajukan dan melihat pencairan saldo sendiri',
            self::PencairanSetujui => 'Menyetujui dan menolak pengajuan pencairan saldo',
        };
    }
}

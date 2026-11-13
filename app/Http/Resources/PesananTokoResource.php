<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Uang;
use App\Models\ButirPesanan;
use App\Models\Pesanan;
use App\Services\LayananDana;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pesanan seperti yang dilihat PENJUALNYA.
 * Yang dilihat penjual: bagiannya setelah komisi dan alamat kirim pembeli.
 *
 * @mixin Pesanan
 */
final class PesananTokoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dana = app(LayananDana::class);
        $komisi = $dana->komisi($this->resource);
        $bagianPenjual = $dana->bagianPenjual($this->resource);
        $total = Uang::dari($this->total_rupiah);

        return [
            'nomor' => $this->nomor,
            'status' => $this->status->value,
            'butir' => $this->butir->map(fn (ButirPesanan $butir) => [
                'produk_id' => $butir->produk_id,
                'nama' => $butir->nama_produk,
                'harga_satuan' => $butir->harga_rupiah,
                'kuantitas' => $butir->kuantitas,
                'subtotal' => $butir->subtotal_rupiah,
            ])->all(),
            'subtotal' => $this->subtotal_rupiah,
            'ongkir' => $this->ongkir_rupiah,
            'total' => ['rupiah' => $total, 'tampilan' => $total->format()],
            'komisi' => ['rupiah' => $komisi, 'tampilan' => $komisi->format()],
            'bagian_penjual' => ['rupiah' => $bagianPenjual, 'tampilan' => $bagianPenjual->format()],
            'alamat_kirim' => $this->alamat_kirim,
            'dibuat_pada' => $this->created_at->toIso8601String(),
            'dibayar_pada' => $this->dibayar_pada?->toIso8601String(),
            'dikemas_pada' => $this->dikemas_pada?->toIso8601String(),
            'dikirim_pada' => $this->dikirim_pada?->toIso8601String(),
            'nomor_resi' => $this->nomor_resi,
            'selesai_pada' => $this->selesai_pada?->toIso8601String(),
            'dibatalkan_pada' => $this->dibatalkan_pada?->toIso8601String(),
            'dibatalkan_oleh' => $this->dibatalkan_oleh,
            'alasan_batal' => $this->alasan_batal,
        ];
    }
}

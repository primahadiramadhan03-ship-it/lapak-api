<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\StatusPesanan;
use App\Domain\Uang;
use App\Enums\JenisDompet;
use App\Enums\JenisMutasi;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pengguna;
use App\Models\Pesanan;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Satu-satunya jalan dana keluar dari dompet escrow (melepas ke penjual atau
 * mengembalikan ke pembeli). Seluruh pemanggil wajib berada di dalam transaksi
 * (GembokCheckout) agar urutan kunci baris pesanan -> dompet terjaga.
 */
final class LayananDana
{
    public function __construct(private readonly BukuBesar $buku) {}

    /**
     * Melepas dana pesanan dari escrow ke dompet penjual (dikurangi komisi ke pendapatan).
     * LP-8: komisi dipotong dari subtotal saja; ongkir diterima utuh oleh penjual.
     *
     * @return array{komisi_rupiah: int, bagian_penjual_rupiah: int}
     */
    public function lepas(Pesanan $pesanan): array
    {
        $this->pastikanDalamTransaksi();

        $terkini = $this->kunciPesanan($pesanan);

        if (! in_array($terkini->status, [StatusPesanan::Dikirim, StatusPesanan::Selesai], true)) {
            throw new LogicException("Dana pesanan berstatus [{$terkini->status->value}] tidak dapat dilepas ke penjual.");
        }

        $penjual = $terkini->toko?->pemilik;
        if (! $penjual instanceof Pengguna) {
            throw new LogicException("Pemilik toko untuk pesanan #{$terkini->id} tidak ditemukan.");
        }

        $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
        $pendapatan = $this->buku->dompetSistem(JenisDompet::Pendapatan);
        $dompetPenjual = $this->buku->cariDompetPenjual($penjual);

        if ($dompetPenjual !== null) {
            $this->kunciDompet($escrow->id, $pendapatan->id, $dompetPenjual->id);
        } else {
            // Kunci dompet sistem lebih dulu agar dua pelepasan serentak untuk penjual
            // yang belum punya dompet tidak saling mendahului (urutan id selalu lebih kecil dari dompet baru).
            $this->kunciDompet($escrow->id, $pendapatan->id);
            $dompetPenjual = $this->buku->dompetPenjualAtauBuat($penjual);
            $this->kunciDompet($dompetPenjual->id);
        }

        $rujukan = $this->rujukanPesanan($terkini);
        $this->pastikanBelumKeluarEscrow($escrow, $rujukan);

        $komisiPersen = (int) config('lapak.komisi_persen', 5);
        $komisi = Uang::dari($terkini->subtotal_rupiah)->persen($komisiPersen);
        $bagianPenjual = Uang::dari($terkini->total_rupiah)->kurang($komisi);

        if ($komisi->rupiah > 0) {
            $this->buku->pindahkan(
                $escrow,
                $pendapatan,
                $komisi->rupiah,
                JenisMutasi::Komisi,
                $rujukan,
                "Komisi {$komisiPersen}% pesanan {$rujukan}",
            );
        }

        if ($bagianPenjual->rupiah > 0) {
            $this->buku->pindahkan(
                $escrow,
                $dompetPenjual,
                $bagianPenjual->rupiah,
                JenisMutasi::LepasDana,
                $rujukan,
                "Pelepasan dana pesanan {$rujukan}",
            );
        }

        return [
            'komisi_rupiah' => $komisi->rupiah,
            'bagian_penjual_rupiah' => $bagianPenjual->rupiah,
        ];
    }

    /**
     * Mengembalikan dana pesanan dari escrow ke dompet eksternal (refund pembeli).
     *
     * @return array{dikembalikan_rupiah: int}
     */
    public function kembalikan(Pesanan $pesanan): array
    {
        $this->pastikanDalamTransaksi();

        $terkini = $this->kunciPesanan($pesanan);

        $bolehDibatalkanBerbayar = in_array(
            $terkini->status,
            [StatusPesanan::Dibayar, StatusPesanan::Dikemas],
            true,
        ) || ($terkini->status === StatusPesanan::Dibatalkan && $terkini->dibayar_pada !== null);

        if (! $bolehDibatalkanBerbayar) {
            throw new LogicException("Dana pesanan berstatus [{$terkini->status->value}] tidak dapat dikembalikan dari escrow.");
        }

        $escrow = $this->buku->dompetSistem(JenisDompet::Escrow);
        $eksternal = $this->buku->dompetSistem(JenisDompet::Eksternal);

        $this->kunciDompet($escrow->id, $eksternal->id);

        $rujukan = $this->rujukanPesanan($terkini);
        $this->pastikanBelumKeluarEscrow($escrow, $rujukan);

        if ($terkini->total_rupiah > 0) {
            $this->buku->pindahkan(
                $escrow,
                $eksternal,
                $terkini->total_rupiah,
                JenisMutasi::Pengembalian,
                $rujukan,
                "Pengembalian dana pesanan {$rujukan}",
            );
        }

        return [
            'dikembalikan_rupiah' => $terkini->total_rupiah,
        ];
    }

    private function pastikanDalamTransaksi(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Operasi dana escrow hanya boleh dijalankan di dalam transaksi.');
        }
    }

    private function kunciPesanan(Pesanan $pesanan): Pesanan
    {
        return Pesanan::query()
            ->with('toko.pemilik')
            ->lockForUpdate()
            ->findOrFail($pesanan->id);
    }

    /**
     * Mengunci seluruh dompet yang terlibat dalam urutan id menaik (anak tangga 7).
     */
    private function kunciDompet(int ...$ids): void
    {
        $unik = array_values(array_unique($ids));
        sort($unik);

        Dompet::query()
            ->whereKey($unik)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function pastikanBelumKeluarEscrow(Dompet $escrow, string $rujukan): void
    {
        $sudahKeluar = MutasiSaldo::query()
            ->where('dompet_id', $escrow->id)
            ->where('rujukan', $rujukan)
            ->whereIn('jenis', [JenisMutasi::LepasDana, JenisMutasi::Komisi, JenisMutasi::Pengembalian])
            ->exists();

        if ($sudahKeluar) {
            throw new LogicException("Dana untuk pesanan [{$rujukan}] sudah pernah dikeluarkan dari escrow.");
        }
    }

    private function rujukanPesanan(Pesanan $pesanan): string
    {
        return $pesanan->nomor ?: 'PSN-'.$pesanan->id;
    }
}

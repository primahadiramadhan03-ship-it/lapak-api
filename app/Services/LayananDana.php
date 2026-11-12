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
 * Jalan keluar dana dari escrow. Ada tepat dua: dilepas kepada penjual, atau dikembalikan
 * kepada pembeli. Keduanya dipanggil di dalam GembokCheckout sesaat setelah status pesanan
 * berubah, sehingga status dan uangnya tidak pernah berbeda cerita.
 */
final class LayananDana
{
    public function __construct(private readonly BukuBesar $buku) {}

    /** LP-8: komisi dihitung dari subtotal produk, dengan pembulatan setengah ke atas milik Uang::persen(). */
    public function komisi(Pesanan $pesanan): Uang
    {
        return Uang::dari($pesanan->subtotal_rupiah)->persen((int) config('lapak.komisi_persen', 5));
    }

    /** Yang diterima penjual: subtotal dikurangi komisi, ditambah ongkir yang utuh miliknya. */
    public function bagianPenjual(Pesanan $pesanan): Uang
    {
        return Uang::dari($pesanan->total_rupiah)->kurang($this->komisi($pesanan));
    }

    /** LP-12: pesanan selesai, dana keluar dari escrow menjadi saldo penjual dan komisi. */
    public function lepaskan(Pesanan $pesanan): void
    {
        $this->pastikanDalamTransaksi();

        $terkini = $this->kunciPesanan($pesanan);

        if ($terkini->status !== StatusPesanan::Selesai) {
            throw new LogicException("Dana hanya dapat dilepas untuk pesanan berstatus selesai (status saat ini: {$terkini->status->value}).");
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
        $komisi = $this->komisi($terkini);
        $bagianPenjual = $this->bagianPenjual($terkini);

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
    }

    /** Pesanan yang sudah dibayar dan berstatus dibatalkan: seluruh nilainya kembali kepada pembeli. */
    public function kembalikan(Pesanan $pesanan): void
    {
        $this->pastikanDalamTransaksi();

        $terkini = $this->kunciPesanan($pesanan);

        if ($terkini->status !== StatusPesanan::Dibatalkan || $terkini->dibayar_pada === null) {
            throw new LogicException("Dana hanya dapat dikembalikan untuk pesanan berbayar yang berstatus dibatalkan (status saat ini: {$terkini->status->value}).");
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
    }

    private function pastikanDalamTransaksi(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Dana hanya boleh dipindahkan di dalam GembokCheckout.');
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
            throw new LogicException("Dana {$rujukan} sudah pernah keluar dari escrow.");
        }
    }

    private function rujukanPesanan(Pesanan $pesanan): string
    {
        return $pesanan->nomor ?: 'PSN-'.$pesanan->id;
    }
}

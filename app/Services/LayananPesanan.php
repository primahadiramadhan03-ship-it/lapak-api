<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Pesanan\PelakuPesanan;
use App\Domain\Pesanan\StatusPesanan;
use App\Enums\PeristiwaAudit;
use App\Exceptions\GalatDomain;
use App\Models\Checkout;
use App\Models\Pengguna;
use App\Models\Pesanan;
use Illuminate\Support\Collection;

/**
 * Satu-satunya pintu perubahan pesanan setelah checkout (LP-10).
 *
 * Mengubah status hampir selalu punya akibat: stok kembali, dana dilepas, dana dikembalikan,
 * jejak audit. Akibat itu ditulis di SINI, satu kali, dengan urutan kunci yang sama untuk semua
 * jalur (docs/PETA-SKEMA.md):
 *
 *     checkout -> pembayaran -> pesanan -> produk urut nomor -> dompet -> log audit
 *
 * Pintu pembeli, pintu penjual, dan penjadwal hanya memanggil kelas ini. Tidak satu pun di
 * antaranya memanggil MesinStatusPesanan, PenjagaStok, atau LayananDana sendiri.
 */
final class LayananPesanan
{
    public function __construct(
        private readonly GembokCheckout $gembok,
        private readonly MesinStatusPesanan $mesin,
        private readonly PenjagaStok $stok,
        private readonly LayananDana $dana,
        private readonly LayananPembayaran $pembayaran,
        private readonly PencatatAudit $audit,
    ) {}

    /** Penjual mulai menyiapkan pesanan yang sudah dibayar. */
    public function kemas(Pesanan $pesanan): Pesanan
    {
        return $this->gembok->dalam($pesanan->checkout_id, fn () => $this->mesin->pindahkan(
            $pesanan, StatusPesanan::Dikemas, PelakuPesanan::Penjual,
        ));
    }

    /** Penjual menyerahkan paket ke kurir. Sejak saat ini pesanan tidak dapat dibatalkan. */
    public function kirim(Pesanan $pesanan, string $nomorResi): Pesanan
    {
        return $this->gembok->dalam($pesanan->checkout_id, function () use ($pesanan, $nomorResi) {
            $terkini = $this->mesin->pindahkan(
                $pesanan, StatusPesanan::Dikirim, PelakuPesanan::Penjual,
            );
            $terkini->forceFill(['nomor_resi' => $nomorResi])->save();

            return $terkini;
        });
    }

    /**
     * LP-12: pembeli mengonfirmasi terima, atau sistem menyelesaikan setelah masa tahan.
     * Status menjadi selesai dan dana dilepas dalam transaksi yang sama.
     */
    public function terima(Pesanan $pesanan, PelakuPesanan $oleh, ?Pengguna $pelaku = null): Pesanan
    {
        return $this->gembok->dalam($pesanan->checkout_id, function () use (
            $pesanan, $oleh, $pelaku,
        ) {
            $terkini = $this->mesin->pindahkan($pesanan, StatusPesanan::Selesai, $oleh);
            $this->dana->lepaskan($terkini);

            $sasaran = "pesanan:{$terkini->nomor}";
            $this->audit->catat(PeristiwaAudit::DanaDilepas, $pelaku, $sasaran, [
                'oleh' => $oleh->value,
                'bagian_penjual_rupiah' => $this->dana->bagianPenjual($terkini)->rupiah,
                'komisi_rupiah' => $this->dana->komisi($terkini)->rupiah,
            ]);

            return $terkini;
        });
    }

    /**
     * Membatalkan SATU pesanan yang sudah dibayar dan belum dikirim. Stoknya kembali dan
     * seluruh nilainya dikembalikan kepada pembeli. Pesanan lain pada checkout yang sama
     * tidak terpengaruh.
     */
    public function batalkan(
        Pesanan $pesanan,
        PelakuPesanan $oleh,
        ?Pengguna $pelaku,
        string $alasan,
    ): Pesanan {
        return $this->gembok->dalam($pesanan->checkout_id, function () use (
            $pesanan, $oleh, $pelaku, $alasan,
        ) {
            $asal = Pesanan::query()->lockForUpdate()->findOrFail($pesanan->id)->status;

            // Sebelum dibayar, satu tagihan berarti satu nasib: yang dibatalkan checkout-nya.
            if ($asal === StatusPesanan::MenungguPembayaran) {
                throw new GalatDomain(
                    'LP-10', 'Pesanan yang belum dibayar dibatalkan bersama checkout-nya.', 409,
                );
            }

            $terkini = $this->mesin->pindahkan($pesanan, StatusPesanan::Dibatalkan, $oleh, $alasan);
            $terkini->load('butir');
            $this->kembalikanStok(collect([$terkini]));
            $this->dana->kembalikan($terkini);

            $sasaran = "pesanan:{$terkini->nomor}";
            $this->audit->catat(PeristiwaAudit::PesananDibatalkan, $pelaku, $sasaran, [
                'oleh' => $oleh->value, 'dari_status' => $asal->value, 'alasan' => $alasan,
            ]);
            $this->audit->catat(PeristiwaAudit::DanaDikembalikan, $pelaku, $sasaran, [
                'jumlah_rupiah' => $terkini->total_rupiah,
            ]);

            return $terkini;
        });
    }

    /**
     * Membatalkan checkout yang BELUM dibayar: seluruh pesanannya batal, tagihannya ditutup,
     * dan stoknya kembali. Oleh pembeli kapan saja sebelum membayar; oleh sistem setelah batas
     * bayar lewat.
     *
     * @return bool false bila tidak ada yang dibatalkan, misalnya pembayarannya baru saja masuk
     */
    public function batalkanCheckout(
        int $idCheckout,
        PelakuPesanan $oleh,
        ?Pengguna $pelaku,
        string $alasan,
    ): bool {
        return $this->gembok->dalam($idCheckout, function (Checkout $checkout) use (
            $oleh, $pelaku, $alasan,
        ): bool {
            // Di bawah gembok, tagihannya diperiksa ulang oleh pemiliknya.
            $ditutup = $oleh === PelakuPesanan::Sistem
                ? $this->pembayaran->tandaiKedaluwarsa($checkout)
                : $this->pembayaran->tandaiDibatalkan($checkout);

            if (! $ditutup) {
                return false;
            }

            $semua = $checkout->pesanan()->with('butir')->orderBy('id')->get();

            foreach ($semua as $pesanan) {
                $this->mesin->pindahkan($pesanan, StatusPesanan::Dibatalkan, $oleh, $alasan);
            }

            $this->kembalikanStok($semua);

            $sasaran = "checkout:{$checkout->kode}";
            $this->audit->catat(PeristiwaAudit::CheckoutDibatalkan, $pelaku, $sasaran, [
                'oleh' => $oleh->value,
                'alasan' => $alasan,
                'pesanan' => $semua->pluck('nomor')->all(),
            ]);

            return true;
        });
    }

    /**
     * Seluruh pesanan sudah berpindah status sebelum metode ini dipanggil. Stok dikembalikan
     * urut nomor produk, sama seperti urutan PenjagaStok::ambil() saat checkout. Urutan lain
     * membuat pembatalan dan checkout saling menunggu selamanya (Sesi 4, Langkah 12).
     *
     * @param  Collection<int, Pesanan>  $pesanan
     */
    private function kembalikanStok(Collection $pesanan): void
    {
        $kembali = [];

        foreach ($pesanan as $satu) {
            foreach ($satu->butir as $butir) {
                $id = $butir->produk_id;
                $kembali[$id] = ($kembali[$id] ?? 0) + $butir->kuantitas;
            }
        }

        ksort($kembali);

        foreach ($kembali as $idProduk => $kuantitas) {
            $this->stok->kembalikan($idProduk, $kuantitas);
        }
    }
}

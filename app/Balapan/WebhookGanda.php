<?php

declare(strict_types=1);

namespace App\Balapan;

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\JenisDompet;
use App\Enums\StatusPembayaran;
use App\Models\Checkout;
use App\Models\Dompet;
use App\Models\MutasiSaldo;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Services\BukuBesar;
use App\Services\TandaTanganGateway;
use Illuminate\Support\Str;

class WebhookGanda extends SkenarioBalapan
{
    use PanggilHttp;

    public const TAGIHAN = 489_500;

    public function nama(): string
    {
        return 'webhook-ganda';
    }

    public function deskripsi(): string
    {
        return 'Kabar pembayaran yang sama tiba serentak: dana masuk escrow tepat satu kali';
    }

    public function jumlahPembalap(): int
    {
        return 6;
    }

    public function siapkan(): array
    {
        $checkout = Checkout::factory()->create(['total_rupiah' => self::TAGIHAN]);
        $tagihan = Pembayaran::factory()->for($checkout)->create(['jumlah_rupiah' => self::TAGIHAN]);
        Pesanan::factory()->for($checkout)->create(['total_rupiah' => 382_000]);
        Pesanan::factory()->for($checkout)->create(['total_rupiah' => 107_500]);

        $badan = json_encode([
            'id_peristiwa' => 'evt_balapan_'.Str::random(10),
            'kode_pembayaran' => $tagihan->kode,
            'jumlah_rupiah' => self::TAGIHAN,
        ]);

        $tanda = app(TandaTanganGateway::class)->buat($badan);

        $escrowAwal = (int) Dompet::query()
            ->where('kode', JenisDompet::Escrow->value)
            ->value('saldo_rupiah');

        return [
            'checkout' => $checkout->id,
            'badan' => $badan,
            'tanda' => $tanda,
            'escrow_awal' => $escrowAwal,
        ];
    }

    public function jalankan(int $indeks, array $konteks): mixed
    {
        $res = $this->panggilJson(
            'POST',
            '/api/v1/webhook/pembayaran',
            json_decode($konteks['badan'], true),
            [TandaTanganGateway::HEADER => $konteks['tanda']],
        );

        $status = $res['status'];
        $json = $res['data'];

        return $status === 200 ? "200 {$json['data']['hasil']}" : "{$status} ".($json['galat']['kode'] ?? 'ERR');
    }

    public function evaluasi(array $hasil, array $konteks): array
    {
        $checkout = Checkout::query()->findOrFail($konteks['checkout']);
        $escrow = app(BukuBesar::class)->dompetSistem(JenisDompet::Escrow)->saldo_rupiah;
        $masuk = $escrow - $konteks['escrow_awal'];
        $nomor = $checkout->pesanan()->pluck('nomor');
        $mutasi = MutasiSaldo::query()->whereIn('rujukan', $nomor)->count();
        $melunasi = count(array_filter($hasil, fn (mixed $h) => $h === '200 dilunasi'));

        $pelanggaran = [];
        if ($melunasi !== 1) {
            $pelanggaran[] = "{$melunasi} kabar dianggap melunasi, seharusnya tepat 1.";
        }
        if ($masuk !== self::TAGIHAN) {
            $pelanggaran[] = "Escrow bertambah {$masuk}, padahal tagihannya ".self::TAGIHAN.'.';
        }
        if ($mutasi !== 4) {
            $pelanggaran[] = "Tercatat {$mutasi} baris mutasi untuk dua pesanan, seharusnya 4.";
        }
        if ($checkout->pesanan()->where('status', StatusPesanan::Dibayar)->count() !== 2) {
            $pelanggaran[] = 'Tidak semua pesanan berstatus dibayar.';
        }
        $tagihan = Pembayaran::query()->where('checkout_id', $checkout->id)->sole();
        if ($tagihan->status !== StatusPembayaran::Lunas) {
            $pelanggaran[] = 'Tagihan tidak berstatus lunas.';
        }

        $sudahDiproses = count(array_filter($hasil, fn (mixed $h) => $h === '200 sudah_diproses'));

        return [
            'sukses' => $melunasi + $sudahDiproses,
            'gagal' => count($pelanggaran),
            'valid' => $pelanggaran === [],
            'pesan' => $pelanggaran === []
                ? count($hasil).' proses serentak, tidak ada aturan yang dilanggar.'
                : implode(' ', $pelanggaran),
            'rincian' => [
                '200 dilunasi' => $melunasi,
                '200 sudah_diproses' => $sudahDiproses,
                'pelanggaran' => $pelanggaran ? implode(', ', $pelanggaran) : 'tidak ada',
            ],
        ];
    }
}

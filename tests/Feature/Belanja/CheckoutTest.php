<?php

use App\Domain\Pesanan\PenomorPesanan;
use App\Enums\StatusProduk;
use App\Enums\StatusToko;
use App\Models\Alamat;
use App\Models\ButirKeranjang;
use App\Models\ButirPesanan;
use App\Models\Checkout;
use App\Models\KunciIdempotensi;
use App\Models\Pembayaran;
use App\Models\Pengguna;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananKeranjang;

/*
| Keadaan awal setiap uji: Andi memiliki alamat dan keranjang berisi dua toko.
|   Batik Bu Sri   : 2 x Kain Batik Cap @ 185.000, ongkir 12.000  -> 382.000
|   Oleh-Oleh Wulan: 3 x Intip Goreng  @  32.500, ongkir 10.000  -> 107.500
|                                                       total bayar  489.500
*/
beforeEach(function () {
    $this->andi = Pengguna::factory()->pembeli()->create();
    $this->alamat = Alamat::factory()->for($this->andi, 'pengguna')->create([
        'nama_penerima' => 'Andi Nugroho',
        'jalan' => 'Jl. Slamet Riyadi No. 12',
    ]);

    $this->sri = Pengguna::factory()->penjual()->create();
    $this->batik = Toko::factory()->for($this->sri, 'pemilik')
        ->create(['nama' => 'Batik Bu Sri', 'ongkir_rupiah' => 12_000]);
    $this->kain = Produk::factory()->for($this->batik)
        ->create(['nama' => 'Kain Batik Cap', 'harga_rupiah' => 185_000, 'stok' => 5]);

    $this->oleh = Toko::factory()->create(['nama' => 'Oleh-Oleh Wulan', 'ongkir_rupiah' => 10_000]);
    $this->intip = Produk::factory()->for($this->oleh)
        ->create(['nama' => 'Intip Goreng', 'harga_rupiah' => 32_500, 'stok' => 10]);

    $keranjang = app(LayananKeranjang::class);
    $keranjang->aturButir($this->andi, $this->kain->id, 2);
    $keranjang->aturButir($this->andi, $this->intip->id, 3);

    $this->checkout = fn (array $badan = [], string $kunci = 'checkout-uji-0000000001') => $this
        ->postJson(
            '/api/v1/checkout',
            $badan + ['alamat_id' => $this->alamat->id],
            ['Idempotency-Key' => $kunci],
        );
});

it('menolak permintaan tanpa kunci sesi, tanpa izin, atau tanpa Idempotency-Key', function () {
    $badan = ['alamat_id' => $this->alamat->id];

    $this->postJson('/api/v1/checkout', $badan)->assertUnauthorized();

    $this->sebagai(Pengguna::factory()->admin()->create());
    ($this->checkout)()->assertForbidden()->assertJsonPath('galat.kode', 'DITOLAK');

    $this->sebagai($this->andi)->postJson('/api/v1/checkout', $badan)
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-11');

    expect(Checkout::count())->toBe(0);
});

it('melahirkan satu pesanan per toko dan satu tagihan untuk semuanya (LP-7)', function () {
    $this->sebagai($this->andi);
    $respons = ($this->checkout)()
        ->assertCreated()
        ->assertJsonPath('data.total', ['rupiah' => 489_500, 'tampilan' => 'Rp 489.500'])
        ->assertJsonPath('data.pembayaran.status', 'menunggu')
        ->assertJsonPath('data.pembayaran.jumlah.rupiah', 489_500)
        ->assertJsonCount(2, 'data.pesanan')
        ->assertJsonPath('data.pesanan.0.toko.nama', 'Batik Bu Sri')
        ->assertJsonPath('data.pesanan.0.status', 'menunggu_pembayaran')
        ->assertJsonPath('data.pesanan.0.total.rupiah', 382_000)
        ->assertJsonPath('data.pesanan.1.total.rupiah', 107_500);

    $checkout = Checkout::sole();

    expect($checkout->total_rupiah)->toBe(489_500)
        ->and($checkout->pengenal_sesi)->toBe($this->sesiUji->pengenal)
        ->and(Pembayaran::sole()->jumlah_rupiah)->toBe(489_500)
        ->and(Pesanan::count())->toBe(2)
        ->and($respons->json('data.pesanan.0.nomor'))->toMatch(PenomorPesanan::POLA)
        // Jumlah seluruh pesanan selalu sama dengan jumlah tagihannya.
        ->and((int) Pesanan::sum('total_rupiah'))->toBe(489_500);
});

it('mengurangi stok dan mengosongkan keranjang dalam langkah yang sama (LP-4)', function () {
    $this->sebagai($this->andi);
    ($this->checkout)()->assertCreated();

    expect($this->kain->refresh()->stok)->toBe(3)
        ->and($this->intip->refresh()->stok)->toBe(7)
        ->and(ButirKeranjang::count())->toBe(0);

    $this->getJson('/api/v1/keranjang')->assertJsonPath('data.pesanan', []);
});

it('mengabaikan harga, kuantitas, total, dan pembeli kiriman klien (LP-6)', function () {
    $orangLain = Pengguna::factory()->pembeli()->create();
    $this->sebagai($this->andi);

    ($this->checkout)([
        'total' => 1, 'total_rupiah' => 1, 'pembeli_id' => $orangLain->id, 'status' => 'dibayar',
        'butir' => [['produk_id' => $this->kain->id, 'kuantitas' => 1, 'harga_satuan' => 1]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.total.rupiah', 489_500)
        ->assertJsonPath('data.pesanan.0.status', 'menunggu_pembayaran');

    expect(Checkout::sole()->pembeli_id)->toBe($this->andi->id)
        ->and(ButirPesanan::query()->where('produk_id', $this->kain->id)->value('kuantitas'))
        ->toBe(2);
});

it('membatalkan SELURUH checkout bila satu butir kehabisan stok', function () {
    // Di antara mengisi keranjang dan menekan bayar, stok kain tinggal satu.
    $this->kain->forceFill(['stok' => 1])->save();
    $this->sebagai($this->andi);

    ($this->checkout)()
        ->assertConflict()
        ->assertJsonPath('galat.kode', 'LP-4')
        ->assertJsonPath('galat.rincian.tersedia', 1);

    // Tidak ada yang tertulis
    expect(Checkout::count())->toBe(0)
        ->and(Pesanan::count())->toBe(0)
        ->and(ButirPesanan::count())->toBe(0)
        ->and($this->kain->refresh()->stok)->toBe(1)
        ->and($this->intip->refresh()->stok)->toBe(10)
        ->and(ButirKeranjang::count())->toBe(2);
});

it('tidak menyisakan apa pun bila langkah terakhir gagal', function () {
    // Pembuatan tagihan (Langkah 4 pada tangga kunci) dipaksa gagal. Pada saat itu checkout, kedua pesanan, butirnya,
    // dan pengurangan stok SUDAH ditulis di dalam transaksi. Semuanya harus ikut batal.
    Pembayaran::creating(fn () => throw new RuntimeException('Tabel pembayaran bermasalah.'));
    $this->sebagai($this->andi);

    ($this->checkout)()->assertStatus(500)->assertJsonPath('galat.kode', 'GALAT_SERVER');

    expect(Checkout::count())->toBe(0)
        ->and(Pesanan::count())->toBe(0)
        ->and(ButirPesanan::count())->toBe(0)
        ->and($this->kain->refresh()->stok)->toBe(5)
        ->and($this->intip->refresh()->stok)->toBe(10)
        ->and(ButirKeranjang::count())->toBe(2)
        ->and(KunciIdempotensi::count())->toBe(0);
});

it('menjawab pengulangan dengan respons yang sama dan efek satu kali (LP-11)', function () {
    $this->sebagai($this->andi);

    $pertama = ($this->checkout)()->assertCreated();
    $kedua = ($this->checkout)()->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

    expect($kedua->json('data.kode'))->toBe($pertama->json('data.kode'))
        ->and($kedua->json('data.total.rupiah'))->toBe($pertama->json('data.total.rupiah'))
        ->and(Checkout::count())->toBe(1)
        ->and(Pesanan::count())->toBe(2)
        ->and($this->kain->refresh()->stok)->toBe(3);
});

it('menolak checkout kedua atas keranjang yang sudah kosong walau kuncinya baru', function () {
    $this->sebagai($this->andi);

    ($this->checkout)()->assertCreated();
    ($this->checkout)([], 'checkout-uji-0000000002')
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'KERANJANG_KOSONG');

    expect(Checkout::count())->toBe(1);
});

it('menuntut sesi segar untuk checkout bernilai besar (LP-15)', function () {
    config(['lapak.transaksi.batas_segar_rupiah' => 400_000]);
    $this->sebagai($this->andi, segar: false);

    ($this->checkout)()->assertForbidden()->assertJsonPath('galat.kode', 'LP-15');

    expect(Checkout::count())->toBe(0)
        ->and($this->kain->refresh()->stok)->toBe(5)
        ->and(ButirKeranjang::count())->toBe(2);

    // Setelah sesi segar, kunci idempotensi yang sama boleh dipakai lagi.
    $this->sebagai($this->andi);
    ($this->checkout)()->assertCreated();
});

it('menolak checkout dengan keranjang kosong', function () {
    $pembeli = Pengguna::factory()->pembeli()->create();
    $alamat = Alamat::factory()->for($pembeli, 'pengguna')->create();
    $this->sebagai($pembeli);

    $this->postJson('/api/v1/checkout', ['alamat_id' => $alamat->id], ['Idempotency-Key' => 'kunci-keranjang-kosong'])
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'KERANJANG_KOSONG');
});

it('menolak checkout bila bentuk masukan alamat tidak sah', function (mixed $alamatId) {
    $this->sebagai($this->andi);

    $this->postJson('/api/v1/checkout', ['alamat_id' => $alamatId], ['Idempotency-Key' => 'kunci-alamat-tidak-sah'])
        ->assertUnprocessable();
})->with([
    'tanpa alamat' => null,
    'alamat bukan integer' => 'bukan-angka',
    'alamat tidak ada' => 999999,
    'alamat milik orang lain' => fn () => Alamat::factory()->create()->id,
]);

it('menolak checkout bila produk tidak aktif atau diarsipkan (LP-4)', function () {
    $this->kain->forceFill(['status' => StatusProduk::Arsip])->save();
    $this->sebagai($this->andi);

    ($this->checkout)()
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-4');
});

it('menolak checkout bila toko sedang ditangguhkan atau tutup (LP-3)', function () {
    $this->batik->forceFill(['status' => StatusToko::Ditangguhkan])->save();
    $this->sebagai($this->andi);

    ($this->checkout)()
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-4');
});

it('menolak checkout jika pembeli mencoba membeli produk dari tokonya sendiri (LP-5)', function () {
    $this->kain->toko->forceFill(['pemilik_id' => $this->andi->id])->save();
    $this->sebagai($this->andi);

    ($this->checkout)()
        ->assertUnprocessable()
        ->assertJsonPath('galat.kode', 'LP-5');
});

it('menolak akses ke rute pesanan saya tanpa autentikasi', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    'daftar pesanan' => '/api/v1/pesanan',
    'rincian pesanan' => '/api/v1/pesanan/LPK-20261110-000001',
]);

it('menolak akses ke rute pesanan saya tanpa izin pesanan.buat', function (string $uri) {
    $this->sebagai(Pengguna::factory()->admin()->create());
    $this->getJson($uri)->assertForbidden()->assertJsonPath('galat.kode', 'DITOLAK');
})->with([
    'daftar pesanan' => '/api/v1/pesanan',
    'rincian pesanan' => '/api/v1/pesanan/LPK-20261110-000001',
]);

it('menampilkan daftar pesanan milik pembeli yang sedang masuk dengan paginasi', function () {
    $this->sebagai($this->andi);
    ($this->checkout)()->assertCreated();

    $pesanan = Pesanan::query()->orderBy('id')->get();

    $respons = $this->getJson('/api/v1/pesanan')
        ->assertOk()
        ->assertJsonStructure([
            'sukses',
            'data' => [
                '*' => [
                    'nomor',
                    'status',
                    'toko' => ['nama', 'slug'],
                    'butir' => [
                        '*' => ['produk_id', 'nama', 'harga_satuan', 'kuantitas', 'subtotal'],
                    ],
                    'subtotal',
                    'ongkir',
                    'total' => ['rupiah', 'tampilan'],
                    'alamat_kirim',
                    'dibuat_pada',
                ],
            ],
            'meta' => ['total', 'halaman', 'per_halaman', 'halaman_terakhir'],
        ])
        ->assertJsonPath('meta.total', 2);

    expect($respons->json('data.0.nomor'))->toBe($pesanan->last()->nomor);
});

it('tidak menampilkan pesanan milik pembeli lain di daftar pesanan', function () {
    $this->sebagai($this->andi);
    ($this->checkout)()->assertCreated();

    $orangLain = Pengguna::factory()->pembeli()->create();
    $this->sebagai($orangLain);

    $this->getJson('/api/v1/pesanan')
        ->assertOk()
        ->assertJsonPath('meta.total', 0)
        ->assertJsonPath('data', []);
});

it('menampilkan rincian pesanan beserta tagihan pembayaran', function () {
    $this->sebagai($this->andi);
    ($this->checkout)()->assertCreated();

    $pesanan = Pesanan::query()->first();

    $this->getJson("/api/v1/pesanan/{$pesanan->nomor}")
        ->assertOk()
        ->assertJsonPath('data.pesanan.nomor', $pesanan->nomor)
        ->assertJsonPath('data.pesanan.status', 'menunggu_pembayaran')
        ->assertJsonPath('data.pembayaran.status', 'menunggu')
        ->assertJsonPath('data.pembayaran.jumlah.rupiah', 489_500);
});

it('menjawab 404 untuk rincian pesanan yang tidak ditemukan atau bukan miliknya', function (string $nomor) {
    $this->sebagai($this->andi);
    ($this->checkout)()->assertCreated();

    $targetNomor = $nomor === 'milik_orang_lain'
        ? Pesanan::query()->first()->nomor
        : $nomor;

    $orangLain = Pengguna::factory()->pembeli()->create();
    $this->sebagai($orangLain);

    $this->getJson("/api/v1/pesanan/{$targetNomor}")
        ->assertNotFound()
        ->assertJsonPath('galat.kode', 'TIDAK_DITEMUKAN');
})->with([
    'nomor tidak ada' => 'LPK-99999999-999999',
    'milik orang lain' => 'milik_orang_lain',
]);

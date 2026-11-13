<?php

declare(strict_types=1);

use App\Domain\Pesanan\StatusPesanan;
use App\Enums\PeristiwaAudit;
use App\Models\LogAudit;
use App\Models\Pengguna;
use App\Models\Produk;
use App\Models\Toko;
use App\Services\LayananPesanan;
use Database\Seeders\DompetSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Pendukung\PesananUji;

beforeEach(function () {
    (new DompetSeeder)->run();

    $this->layanan = app(LayananPesanan::class);

    $this->pembeli = Pengguna::factory()->pembeli()->create(['nama' => 'Andi Pembeli']);
    $this->penjual = Pengguna::factory()->penjual()->create(['nama' => 'Sri Penjual']);
    $this->toko = Toko::factory()->for($this->penjual, 'pemilik')->create(['nama' => 'Batik Laweyan']);
    $this->produk = Produk::factory()->for($this->toko)->create([
        'nama' => 'Kain Batik Tulis',
        'harga_rupiah' => PesananUji::HARGA,
        'stok' => 10,
    ]);

    $this->penjualLain = Pengguna::factory()->penjual()->create(['nama' => 'Budi Penjual']);
    $this->tokoLain = Toko::factory()->for($this->penjualLain, 'pemilik')->create(['nama' => 'Toko Kain Budi']);
    $this->produkLain = Produk::factory()->for($this->tokoLain)->create([
        'nama' => 'Kain Tenun',
        'harga_rupiah' => PesananUji::HARGA,
        'stok' => 5,
    ]);
});

it('menolak akses daftar pesanan tanpa kunci sesi', function () {
    $this->tanpaKunci()->getJson('/api/v1/toko-saya/pesanan')->assertUnauthorized();
});

it('menolak akses daftar pesanan untuk pengguna tanpa izin pesanan.proses', function () {
    $this->sebagai($this->pembeli)
        ->getJson('/api/v1/toko-saya/pesanan')
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
});

it('menjawab 404 pada daftar pesanan bila penjual belum memiliki toko', function () {
    $penjualTanpaToko = Pengguna::factory()->penjual()->create();

    $this->sebagai($penjualTanpaToko)
        ->getJson('/api/v1/toko-saya/pesanan')
        ->assertNotFound();
});

it('menampilkan daftar pesanan toko dengan pagination, urutan terbaru, dan penyaringan status', function () {
    $pesanan1 = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $pesanan2 = PesananUji::dibayar($this->pembeli, $this->produk, 2);
    $this->layanan->kemas($pesanan2);

    // Tanpa filter status: memuat keduanya berurutan dari id terbaru
    $this->sebagai($this->penjual)
        ->getJson('/api/v1/toko-saya/pesanan')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.nomor', $pesanan2->nomor)
        ->assertJsonPath('data.1.nomor', $pesanan1->nomor);

    // Dengan filter status=dikemas: hanya memuat pesanan2
    $this->sebagai($this->penjual)
        ->getJson('/api/v1/toko-saya/pesanan?status=dikemas')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nomor', $pesanan2->nomor)
        ->assertJsonPath('data.0.status', 'dikemas');
});

it('mengisolasi pesanan sehingga penjual tidak melihat pesanan toko lain', function () {
    $pesananSaya = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $pesananLain = PesananUji::dibayar($this->pembeli, $this->produkLain, 1);

    $respons = $this->sebagai($this->penjual)
        ->getJson('/api/v1/toko-saya/pesanan')
        ->assertOk();

    $nomorDaftar = collect($respons->json('data'))->pluck('nomor');
    expect($nomorDaftar)->toContain($pesananSaya->nomor)
        ->and($nomorDaftar)->not->toContain($pesananLain->nomor);
});

it('menyajikan rincian keuangan, daftar butir, dan tindakan yang tersedia bagi penjual', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 2);

    $respons = $this->sebagai($this->penjual)
        ->getJson('/api/v1/toko-saya/pesanan')
        ->assertOk()
        ->assertJsonPath('data.0.subtotal', 370_000)
        ->assertJsonPath('data.0.ongkir', 12_000)
        ->assertJsonPath('data.0.total.rupiah', 382_000)
        ->assertJsonPath('data.0.total.tampilan', 'Rp 382.000')
        ->assertJsonPath('data.0.komisi.rupiah', 18_500)
        ->assertJsonPath('data.0.komisi.tampilan', 'Rp 18.500')
        ->assertJsonPath('data.0.bagian_penjual.rupiah', 363_500)
        ->assertJsonPath('data.0.bagian_penjual.tampilan', 'Rp 363.500')
        ->assertJsonPath('data.0.tindakan', ['dikemas', 'dibatalkan'])
        ->assertJsonCount(1, 'data.0.butir')
        ->assertJsonPath('data.0.butir.0.nama', 'Kain Batik Tulis');

    expect($respons->json('data.0.alamat_kirim'))->toEqualCanonicalizing($pesanan->alamat_kirim);
});

it('menyembunyikan alamat kirim pembeli sebelum pesanan dibayar demi privasi', function () {
    $pesananBelumBayar = PesananUji::belumDibayar($this->pembeli, $this->produk, 1);
    $pesananLunas = PesananUji::dibayar($this->pembeli, $this->produk, 1);

    // Pesanan belum dibayar: alamat_kirim null
    $this->sebagai($this->penjual)
        ->getJson("/api/v1/toko-saya/pesanan/{$pesananBelumBayar->nomor}")
        ->assertOk()
        ->assertJsonPath('data.alamat_kirim', null)
        ->assertJsonPath('data.tindakan', []);

    // Pesanan sudah dibayar: alamat_kirim tampil
    $respons = $this->sebagai($this->penjual)
        ->getJson("/api/v1/toko-saya/pesanan/{$pesananLunas->nomor}")
        ->assertOk();

    expect($respons->json('data.alamat_kirim'))->toEqualCanonicalizing($pesananLunas->alamat_kirim)
        ->and($respons->json('data.tindakan'))->toBe(['dikemas', 'dibatalkan']);
});

it('menolak akses detail pesanan tanpa kunci sesi atau izin yang tepat', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);

    $this->tanpaKunci()
        ->getJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}")
        ->assertUnauthorized();

    $this->sebagai($this->pembeli)
        ->getJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}")
        ->assertForbidden()
        ->assertJsonPath('galat.kode', 'DITOLAK');
});

it('menampilkan detail pesanan toko berdasarkan nomor pesanan', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);

    $this->sebagai($this->penjual)
        ->getJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}")
        ->assertOk()
        ->assertJsonPath('data.nomor', $pesanan->nomor)
        ->assertJsonPath('data.status', StatusPesanan::Dibayar->value);
});

it('menampilkan detail pesanan toko berdasarkan id numerik', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);

    $this->sebagai($this->penjual)
        ->getJson("/api/v1/toko-saya/pesanan/{$pesanan->id}")
        ->assertOk()
        ->assertJsonPath('data.nomor', $pesanan->nomor);
});

it('menjawab 404 untuk detail pesanan milik toko lain', function () {
    $pesananLain = PesananUji::dibayar($this->pembeli, $this->produkLain, 1);

    $this->sebagai($this->penjual)
        ->getJson("/api/v1/toko-saya/pesanan/{$pesananLain->nomor}")
        ->assertNotFound();
});

it('menjawab 404 untuk detail pesanan yang tidak ditemukan', function () {
    $this->sebagai($this->penjual)
        ->getJson('/api/v1/toko-saya/pesanan/NON-EXISTENT-ORDER')
        ->assertNotFound();
});

it('penjual berhasil mengemas pesanan yang sudah dibayar tanpa idempoten (LP-10)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kemas")
        ->assertOk()
        ->assertJsonPath('data.status', StatusPesanan::Dikemas->value)
        ->assertJsonPath('data.nomor', $pesanan->nomor)
        ->assertJsonPath('data.tindakan', ['dikirim', 'dibatalkan']);

    expect($pesanan->refresh()->status)->toBe(StatusPesanan::Dikemas)
        ->and($pesanan->dikemas_pada)->not->toBeNull();
});

it('menolak pengemasan ulang pesanan yang sudah dikemas dengan 409 (LP-10)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $this->layanan->kemas($pesanan);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kemas")
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('menolak pengemasan pesanan yang belum dibayar dengan 409 (LP-10)', function () {
    $pesanan = PesananUji::belumDibayar($this->pembeli, $this->produk, 1);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kemas")
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('menjawab 404 saat mengemas pesanan milik toko lain', function () {
    $pesananLain = PesananUji::dibayar($this->pembeli, $this->produkLain, 1);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesananLain->nomor}/kemas")
        ->assertNotFound();
});

it('menolak pengiriman pesanan tanpa nomor resi yang sah', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $this->layanan->kemas($pesanan);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kirim", [])
        ->assertStatus(422)
        ->assertJsonPath('galat.kode', 'VALIDASI')
        ->assertJsonStructure(['galat' => ['rincian' => ['nomor_resi']]]);
});

it('penjual berhasil mengirim pesanan yang sudah dikemas dengan nomor resi (LP-10)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $this->layanan->kemas($pesanan);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kirim", [
            'nomor_resi' => 'JNE-REG-987654321',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', StatusPesanan::Dikirim->value)
        ->assertJsonPath('data.nomor_resi', 'JNE-REG-987654321')
        ->assertJsonPath('data.tindakan', []);

    expect($pesanan->refresh())
        ->status->toBe(StatusPesanan::Dikirim)
        ->nomor_resi->toBe('JNE-REG-987654321')
        ->dikirim_pada->not->toBeNull();
});

it('menolak pengiriman ulang pesanan yang sudah dikirim dengan 409 (LP-10)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $this->layanan->kemas($pesanan);
    $this->layanan->kirim($pesanan, 'JNE-REG-1111');

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kirim", [
            'nomor_resi' => 'JNE-REG-2222',
        ])
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('menolak pengiriman pesanan yang belum dikemas dengan 409 (LP-10)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/kirim", [
            'nomor_resi' => 'JNE-GAGAL-01',
        ])
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('penjual membatalkan pesanan sebelum dikirim dan mendukung idempotensi (LP-10, LP-18)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 2);
    $stokAwal = $this->produk->stok;

    $payload = ['alasan' => 'Stok produk rusak saat pemeriksaan gudang.'];

    $this->sebagai($this->penjual)
        ->withHeader('Idempotency-Key', 'BATAL-IDEMP-01')
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/batal", $payload)
        ->assertOk()
        ->assertJsonPath('data.status', StatusPesanan::Dibatalkan->value)
        ->assertJsonPath('data.alasan_batal', 'Stok produk rusak saat pemeriksaan gudang.')
        ->assertJsonPath('data.dibatalkan_oleh', 'penjual')
        ->assertJsonPath('data.tindakan', []);

    // Pemanggilan ulang dengan Idempotency-Key yang sama (replay)
    $this->sebagai($this->penjual)
        ->withHeader('Idempotency-Key', 'BATAL-IDEMP-01')
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/batal", $payload)
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true');

    // Stok kembali
    expect($this->produk->refresh()->stok)->toBe($stokAwal + 2);

    // Dana kembali dari escrow ke eksternal
    $saldoEscrowAkhir = (int) DB::table('dompet')->where('kode', 'escrow')->value('saldo_rupiah');
    $saldoEksternalAkhir = (int) DB::table('dompet')->where('kode', 'eksternal')->value('saldo_rupiah');

    expect($saldoEscrowAkhir)->toBe(0)
        ->and($saldoEksternalAkhir)->toBe(0);

    // Audit peristiwa tercatat
    expect(LogAudit::query()->where('peristiwa', PeristiwaAudit::PesananDibatalkan->value)->exists())->toBeTrue()
        ->and(LogAudit::query()->where('peristiwa', PeristiwaAudit::DanaDikembalikan->value)->exists())->toBeTrue();
});

it('menolak pembatalan pesanan yang sudah dikirim dengan 409 (LP-10)', function () {
    $pesanan = PesananUji::dibayar($this->pembeli, $this->produk, 1);
    $this->layanan->kemas($pesanan);
    $this->layanan->kirim($pesanan, 'RESI-12345');

    $this->sebagai($this->penjual)
        ->postJson("/api/v1/toko-saya/pesanan/{$pesanan->nomor}/batal", [
            'alasan' => 'Mencoba batalkan pesanan setelah dikirim kurir.',
        ])
        ->assertStatus(409)
        ->assertJsonPath('galat.kode', 'LP-10');
});

it('mencatat jejak audit saat admin menyetujui atau menangguhkan toko (LP-17)', function () {
    $admin = Pengguna::factory()->admin()->create();
    $pemilik = Pengguna::factory()->pembeli()->create();
    $toko = Toko::factory()->menunggu()->for($pemilik, 'pemilik')->create(['nama' => 'Toko Audit Uji']);

    // Admin menyetujui toko
    $this->sebagai($admin)->postJson("/api/v1/admin/toko/{$toko->id}/setujui")
        ->assertOk();

    expect(LogAudit::query()->where('peristiwa', PeristiwaAudit::TokoDisetujui->value)->latest('id')->first())
        ->not->toBeNull()
        ->pelaku_id->toBe($admin->id)
        ->sasaran->toBe("toko:{$toko->id}")
        ->rincian->toMatchArray([
            'slug' => $toko->slug,
        ]);

    // Admin menangguhkan toko
    $this->sebagai($admin)->postJson("/api/v1/admin/toko/{$toko->id}/tangguhkan", [
        'alasan' => 'Melanggar ketentuan layanan platform.',
    ])->assertOk();

    expect(LogAudit::query()->where('peristiwa', PeristiwaAudit::TokoDitangguhkan->value)->latest('id')->first())
        ->not->toBeNull()
        ->pelaku_id->toBe($admin->id)
        ->sasaran->toBe("toko:{$toko->id}")
        ->rincian->toMatchArray([
            'slug' => $toko->slug,
            'alasan' => 'Melanggar ketentuan layanan platform.',
        ]);
});

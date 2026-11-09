<?php

declare(strict_types=1);

use App\Enums\NamaIzin;
use App\Enums\NamaPeran;
use App\Http\Middleware\PastikanIzin;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Services\PemeriksaIzin;
use Database\Seeders\PeranIzinSeeder;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

describe('Matriks Otorisasi', function () {
    beforeEach(function () {
        $this->seed(PeranIzinSeeder::class);

        $this->lampirkanPeran = function (Pengguna $pengguna, NamaPeran $nama): void {
            Peran::query()
                ->where('nama', $nama->value)
                ->firstOrFail()
                ->pengguna()
                ->attach($pengguna->getKey());
        };

        $this->muatMatriks = function (): array {
            $berkas = glob(__DIR__.'/matriks/*.php') ?: [];
            $matriks = [];

            foreach ($berkas as $path) {
                $isi = require $path;

                foreach ($isi as $rute => $izin) {
                    $matriks[$rute] = $izin;
                }
            }

            return $matriks;
        };
    });

    it('mendefinisikan seluruh peran dan izin yang disepakati', function () {
        expect(array_map(static fn (NamaPeran $peran): string => $peran->value, NamaPeran::cases()))
            ->toBe(['pembeli', 'penjual', 'admin', 'keuangan', 'auditor'])
            ->and(array_map(static fn (NamaIzin $izin): string => $izin->value, NamaIzin::cases()))
            ->toBe([
                'keranjang.kelola',
                'toko.buka',
                'pesanan.buat',
                'produk.kelola',
                'dompet.lihat',
                'toko.moderasi',
                'peran.kelola',
                'buku-besar.lihat',
            ]);
    });

    it('memetakan izin per peran sesuai matriks yang disepakati', function () {
        expect(NamaPeran::Pembeli->izinBawaan())->toEqualCanonicalizing([
            NamaIzin::KeranjangKelola,
            NamaIzin::TokoBuka,
            NamaIzin::PesananBuat,
        ])->and(NamaPeran::Penjual->izinBawaan())->toEqualCanonicalizing([
            NamaIzin::ProdukKelola,
            NamaIzin::DompetLihat,
        ])->and(NamaPeran::Admin->izinBawaan())->toEqualCanonicalizing([
            NamaIzin::TokoModerasi,
            NamaIzin::PeranKelola,
        ])->and(NamaPeran::Keuangan->izinBawaan())->toEqualCanonicalizing([
            NamaIzin::BukuBesarLihat,
        ])->and(NamaPeran::Auditor->izinBawaan())->toEqualCanonicalizing([
            NamaIzin::BukuBesarLihat,
        ]);
    });

    it('memastikan setiap izin dimiliki sedikitnya satu peran', function () {
        $izinTerpakai = [];

        foreach (NamaPeran::cases() as $peran) {
            foreach ($peran->izinBawaan() as $izin) {
                $izinTerpakai[] = $izin->value;
            }
        }

        expect(array_unique($izinTerpakai))->toEqualCanonicalizing(
            array_map(static fn (NamaIzin $izin): string => $izin->value, NamaIzin::cases()),
        );
    });

    it('mengisi peran, izin, dan pivot secara idempotent', function () {
        $this->seed(PeranIzinSeeder::class);

        expect(Peran::query()->count())->toBe(count(NamaPeran::cases()))
            ->and(Izin::query()->count())->toBe(count(NamaIzin::cases()));

        foreach (NamaPeran::cases() as $peran) {
            $tersimpan = Peran::query()->where('nama', $peran->value)->firstOrFail();

            expect($tersimpan->izin()->pluck('nama')->map(
                static fn (NamaIzin $izin): string => $izin->value,
            )->all())->toEqualCanonicalizing(
                array_map(static fn (NamaIzin $izin): string => $izin->value, $peran->izinBawaan()),
            );
        }
    });

    it('menyediakan kolom diberikan_oleh yang nullable pada pengguna_peran', function () {
        $kolom = collect(Schema::getColumns('pengguna_peran'))
            ->firstWhere('name', 'diberikan_oleh');

        expect($kolom)->not->toBeNull()
            ->and($kolom['nullable'])->toBeTrue();
    });

    it('menggabungkan izin dari seluruh peran pengguna tanpa duplikat', function () {
        $pengguna = Pengguna::factory()->create();
        ($this->lampirkanPeran)($pengguna, NamaPeran::Admin);
        ($this->lampirkanPeran)($pengguna, NamaPeran::Keuangan);
        ($this->lampirkanPeran)($pengguna, NamaPeran::Auditor);

        expect(app(PemeriksaIzin::class)->daftar($pengguna))->toEqualCanonicalizing([
            NamaIzin::TokoModerasi->value,
            NamaIzin::PeranKelola->value,
            NamaIzin::BukuBesarLihat->value,
        ]);
    });

    it('menyediakan hook lupakan yang dapat dipanggil', function () {
        $pengguna = Pengguna::factory()->create();

        app(PemeriksaIzin::class)->lupakan($pengguna);

        expect(true)->toBeTrue();
    });

    it('memeriksa izin sebelum substitute bindings', function () {
        $prioritas = app(HttpKernel::class)->getMiddlewarePriority();

        $posisiIzin = array_search(PastikanIzin::class, $prioritas, true);
        $posisiBinding = array_search(SubstituteBindings::class, $prioritas, true);

        expect($posisiIzin)->not->toBeFalse()
            ->and($posisiBinding)->not->toBeFalse()
            ->and($posisiIzin)->toBeLessThan($posisiBinding);
    });

    it('menyediakan empat berkas matriks domain', function () {
        $berkas = array_map(
            static fn (string $path): string => basename($path),
            glob(__DIR__.'/matriks/*.php') ?: [],
        );

        expect($berkas)->toEqualCanonicalizing([
            'identitas.php',
            'katalog.php',
            'belanja.php',
            'dompet.php',
        ]);
    });
    it('mengisi matriks awal dengan wildcard pada rute yang terdaftar', function () {
        $matriks = ($this->muatMatriks)();

        expect($matriks)->not->toBeEmpty();

        $dikenal = array_map(static fn (NamaPeran $peran): string => $peran->value, NamaPeran::cases());

        foreach ($matriks as $rute => $izin) {
            expect($rute)->toMatch('/^(GET|POST|PUT|PATCH|DELETE) \/api\/v1\//');

            if ($izin !== '*') {
                expect($izin)->toBeArray()
                    ->and(array_diff($izin, $dikenal))->toBeEmpty();
            }
        }

        $terdaftar = collect(Route::getRoutes()->getRoutes())
            ->flatMap(static fn ($rute): array => array_map(
                static fn (string $metode): string => $metode.' /'.$rute->uri(),
                array_diff($rute->methods(), ['HEAD', 'OPTIONS']),
            ))
            ->unique()
            ->values()
            ->all();

        expect(array_diff(array_keys($matriks), $terdaftar))->toBe([]);
    });

    it('mencakup seluruh rute api yang dilindungi auth:sesi', function () {
        $matriks = ($this->muatMatriks)();

        $terlindung = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($rute): bool => in_array('auth:sesi', $rute->gatherMiddleware(), true))
            ->flatMap(static fn ($rute): array => array_map(
                static fn (string $metode): string => $metode.' /'.$rute->uri(),
                array_diff($rute->methods(), ['HEAD', 'OPTIONS']),
            ))
            ->unique()
            ->values()
            ->all();

        expect(array_keys($matriks))->toEqualCanonicalizing($terlindung);
    });

    it('mengizinkan pengguna yang memegang izin melalui perannya', function () {
        $pengguna = Pengguna::factory()->create();
        ($this->lampirkanPeran)($pengguna, NamaPeran::Admin);

        Route::middleware(['auth:sesi', 'izin:toko.moderasi'])
            ->get('/uji-izin-diperbolehkan', fn () => response()->json(['ok' => true]));

        $this->sebagai($pengguna)
            ->getJson('/uji-izin-diperbolehkan')
            ->assertOk()
            ->assertJsonPath('ok', true);
    });

    it('menolak pengguna yang tidak memegang izin dengan kode DITOLAK', function () {
        $pengguna = Pengguna::factory()->create();
        ($this->lampirkanPeran)($pengguna, NamaPeran::Pembeli);

        Route::middleware(['auth:sesi', 'izin:toko.moderasi'])
            ->get('/uji-izin-ditolak', fn () => response()->json(['ok' => true]));

        $this->sebagai($pengguna)
            ->getJson('/uji-izin-ditolak')
            ->assertForbidden()
            ->assertJsonPath('galat.kode', 'DITOLAK');
    });

    it('menolak nama izin yang tidak dikenal tanpa galat server', function () {
        $pengguna = Pengguna::factory()->create();
        ($this->lampirkanPeran)($pengguna, NamaPeran::Admin);

        Route::middleware(['auth:sesi', 'izin:tidak.dikenal'])
            ->get('/uji-izin-tidak-dikenal', fn () => response()->json(['ok' => true]));

        $this->sebagai($pengguna)
            ->getJson('/uji-izin-tidak-dikenal')
            ->assertForbidden()
            ->assertJsonPath('galat.kode', 'DITOLAK');
    });

    it('tetap menolak pengguna tanpa sesi sebelum memeriksa izin', function () {
        Route::middleware(['auth:sesi', 'izin:toko.moderasi'])
            ->get('/uji-izin-tanpa-sesi', fn () => response()->json(['ok' => true]));

        $this->tanpaKunci()
            ->getJson('/uji-izin-tanpa-sesi')
            ->assertUnauthorized()
            ->assertJsonPath('galat.kode', 'BELUM_MASUK');
    });

    it('menampilkan matriks melalui command lapak:matriks-izin', function () {
        $this->artisan('lapak:matriks-izin')
            ->expectsOutputToContain('keranjang.kelola')
            ->expectsOutputToContain('buku-besar.lihat')
            ->assertSuccessful();
    });
});

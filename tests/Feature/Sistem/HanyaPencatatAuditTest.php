<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Uji arsitektur pintu tunggal keempat (LP-19).
 *
 * Rantai audit hanya berguna bila tidak ada jalan lain menulis ke tabelnya. Uji ini membaca
 * seluruh berkas aplikasi dan menolak setiap penulisan `log_audit` di luar `PencatatAudit`.
 * Menambah penulis kedua berarti rantai kehilangan artinya, sekalipun semua uji lain hijau.
 */
it('hanya PencatatAudit yang menulis log audit', function () {
    $pengecualian = [
        'Services/PencatatAudit.php',
        'Models/LogAudit.php',
    ];

    $pola = [
        '/new\s+LogAudit\b/' => 'membuat LogAudit langsung',
        '/LogAudit::create\s*\(/' => 'memanggil LogAudit::create',
        '/LogAudit::insert\s*\(/' => 'memanggil LogAudit::insert',
        '/DB::table\(\s*[\'"]log_audit[\'"]\s*\)\s*->\s*(insert|update|delete|upsert|truncate)/' => 'menulis log_audit lewat DB::table',
    ];

    $pelanggaran = [];

    foreach (File::allFiles(app_path()) as $berkas) {
        $relatif = str_replace('\\', '/', $berkas->getRelativePathname());

        if (in_array($relatif, $pengecualian, true)) {
            continue;
        }

        $isi = $berkas->getContents();

        foreach ($pola as $ekspresi => $pesan) {
            if (preg_match($ekspresi, $isi) === 1) {
                $pelanggaran[] = "{$relatif}: {$pesan}.";
            }
        }
    }

    expect($pelanggaran)->toBe([]);
});

it('menyimpan satu-satunya pemeriksa rantai di domain audit', function () {
    $berkas = array_map(
        static fn (string $path): string => basename($path),
        glob(app_path('Domain/Audit/*.php')) ?: [],
    );

    expect($berkas)->toEqualCanonicalizing([
        'PemeriksaRantaiAudit.php',
        'RantaiHash.php',
    ]);
});

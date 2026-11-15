<?php

declare(strict_types=1);

use App\Enums\PeristiwaAudit;
use Illuminate\Support\Facades\File;

it('memastikan versi aplikasi terdefinisi 1.0.0', function () {
    expect(config('lapak.versi'))->toBe('1.0.0');
});

it('tidak menyimpan peristiwa audit yang menganggur', function () {
    $semuaPeristiwa = array_map(fn (PeristiwaAudit $p) => $p->value, PeristiwaAudit::cases());

    $terpakai = [];
    $polaKasus = '/PeristiwaAudit::([A-Za-z0-9_]+)/';

    foreach (File::allFiles(app_path()) as $berkas) {
        $isi = $berkas->getContents();
        if (preg_match_all($polaKasus, $isi, $matches)) {
            foreach ($matches[1] as $namaKasus) {
                if (defined(PeristiwaAudit::class.'::'.$namaKasus)) {
                    $terpakai[] = constant(PeristiwaAudit::class.'::'.$namaKasus)->value;
                }
            }
        }
    }

    $terpakai = array_unique($terpakai);
    $menganggur = array_values(array_diff($semuaPeristiwa, $terpakai));

    expect($menganggur)->toBeEmpty('Peristiwa audit tanpa pemanggil di app/: '.implode(', ', $menganggur));
});

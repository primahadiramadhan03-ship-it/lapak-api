<?php

declare(strict_types=1);

test('arsitektur: seluruh migration dilarang menggunakan float, double, atau decimal (LP-1)', function () {
    $berkasMigrasi = glob(database_path('migrations/*.php')) ?: [];

    expect($berkasMigrasi)->not->toBeEmpty();

    foreach ($berkasMigrasi as $berkas) {
        $konten = file_get_contents($berkas);

        expect($konten)->not->toMatch(
            '/->(float|double|decimal)\(/i',
            "Berkas migrasi [{$berkas}] melanggar aturan LP-1 karena menggunakan tipe pecahan (float/double/decimal)."
        );
    }
});

it('hanya mengubah peran pengguna lewat PengelolaPeran (LP-2)', function () {
    $pelanggar = [];
    $berkas = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($berkas as $satu) {
        if (! $satu->isFile() || $satu->getFilename() === 'PengelolaPeran.php') {
            continue;
        }

        $isi = file_get_contents($satu->getPathname());
        $lewatRelasi = '/->peran\(\)\s*->(attach|detach|sync\w*|toggle|updateExistingPivot)\(/';
        $lewatTabel = '/table\(\'pengguna_peran\'\)\s*->(insert\w*|update|delete|upsert)\(/';

        $menulis = preg_match($lewatRelasi, $isi) || preg_match($lewatTabel, $isi);

        if ($menulis) {
            $pelanggar[] = $satu->getFilename();
        }
    }

    expect($pelanggar)->toBeEmpty('Menulis peran di luar PengelolaPeran: '.implode(', ', $pelanggar));
});

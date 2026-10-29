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

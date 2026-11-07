<?php

declare(strict_types=1);

use App\Models\Peran;

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

test('arsitektur: peran hanya dapat diubah melalui PengelolaPeran (B2)', function () {
    $peranModel = new Peran;
    $reflection = new ReflectionClass($peranModel);

    // Peran model hanya boleh punya BelongsToMany relations
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $relationMethods = [];

    foreach ($methods as $method) {
        $returnType = $method->getReturnType();
        if ($returnType && strpos((string) $returnType, 'BelongsToMany') !== false) {
            $relationMethods[] = $method->getName();
        }
    }

    // Verifikasi pengguna() dan izin() relations exist (read-only)
    expect($relationMethods)->toContain('pengguna')
        ->and($relationMethods)->toContain('izin');

    // Verifikasi Peran model tidak punya save/update/delete yang override parent
    $publicMethods = array_map(fn ($m) => $m->getName(), $methods);
    $forbiddenMethods = ['save', 'delete', 'update', 'create', 'forceCreate', 'firstOrCreate', 'updateOrCreate'];

    foreach ($forbiddenMethods as $forbidden) {
        $hasForbidden = in_array($forbidden, $publicMethods) &&
            $reflection->getMethod($forbidden)->getDeclaringClass()->getName() === Peran::class;

        expect($hasForbidden)->toBeFalse(
            "Peran model tidak boleh override method {$forbidden}. Gunakan PengelolaPeran untuk modifikasi."
        );
    }
});

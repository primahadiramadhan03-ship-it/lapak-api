<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunci_idempotensi', function (Blueprint $tabel) {
            $tabel->id();

            // Kunci idempotensi terikat pada pengguna (LP-11 / Peta Skema D5).
            $tabel->foreignId('pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->cascadeOnDelete();

            $tabel->string('kunci', 100);
            $tabel->string('metode', 10);
            $tabel->string('jalur', 255);

            // Hash SHA-256 payload untuk membedakan kiriman berbeda dengan kunci yang sama.
            $tabel->char('hash_permintaan', 64);

            $tabel->unsignedSmallInteger('status_kode')->nullable();
            $tabel->json('respons')->nullable();

            $tabel->timestamps();

            // Mencegah balapan: satu pengguna tidak boleh memiliki kunci ganda.
            $tabel->unique(['pengguna_id', 'kunci']);
            $tabel->index('kunci');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunci_idempotensi');
    }
};

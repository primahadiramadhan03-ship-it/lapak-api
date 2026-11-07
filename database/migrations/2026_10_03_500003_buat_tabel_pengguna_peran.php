<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengguna_peran', function (Blueprint $tabel) {
            $tabel->id();

            $tabel->foreignId('pengguna_id')
                ->constrained('pengguna')
                ->cascadeOnDelete();

            $tabel->foreignId('peran_id')
                ->constrained('peran')
                ->cascadeOnDelete();

            $tabel->foreignId('diberikan_oleh')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete();

            $tabel->timestamps();

            $tabel->unique(['pengguna_id', 'peran_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengguna_peran');
    }
};

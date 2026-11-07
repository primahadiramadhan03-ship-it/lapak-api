<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peran_izin', function (Blueprint $tabel) {
            $tabel->id();

            $tabel->foreignId('peran_id')
                ->constrained('peran')
                ->cascadeOnDelete();

            $tabel->foreignId('izin_id')
                ->constrained('izin')
                ->cascadeOnDelete();

            $tabel->timestamps();

            $tabel->unique(['peran_id', 'izin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peran_izin');
    }
};

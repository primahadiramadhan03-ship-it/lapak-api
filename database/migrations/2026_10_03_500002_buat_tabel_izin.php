<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('nama', 80)->unique();
            $tabel->string('keterangan', 255)->nullable();
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin');
    }
};

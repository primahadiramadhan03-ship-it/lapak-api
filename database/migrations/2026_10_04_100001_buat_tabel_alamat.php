<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alamat', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pengguna_id')
                ->constrained('pengguna')
                ->cascadeOnDelete();

            $table->string('label', 30);
            $table->string('nama_penerima', 80);
            $table->string('telepon', 20);
            $table->string('jalan', 200);
            $table->string('kota', 60);
            $table->string('kode_pos', 5);
            $table->boolean('utama')->default(false);

            $table->timestamps();
            $table->index(['pengguna_id', 'utama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alamat');
    }
};

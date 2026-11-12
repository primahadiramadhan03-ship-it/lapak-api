<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pengajuan pencairan saldo penjual ke rekening tujuan.
     * Putusan dilakukan oleh staf keuangan berbeda dari pemohon (aturan empat mata).
     */
    public function up(): void
    {
        Schema::create('pencairan', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('kode', 30)->unique();
            $tabel->foreignId('pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $tabel->foreignId('dompet_id')->constrained('dompet')->restrictOnDelete();
            $tabel->unsignedBigInteger('jumlah_rupiah');
            $tabel->string('rekening_tujuan', 160);
            $tabel->string('status', 20)->index();
            $tabel->foreignId('diputuskan_oleh')->nullable()->constrained('pengguna')->restrictOnDelete();
            $tabel->string('alasan_tolak', 200)->nullable();
            $tabel->timestamp('diputuskan_pada')->nullable();
            $tabel->timestamps();

            $tabel->index(['pengguna_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan');
    }
};

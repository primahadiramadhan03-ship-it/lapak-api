<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LP-18: Tabel pengajuan pencairan saldo penjual ke rekening tujuan.
     * Putusan dilakukan oleh staf keuangan berbeda dari pengaju (aturan empat mata).
     */
    public function up(): void
    {
        Schema::create('pencairan', function (Blueprint $tabel) {
            $tabel->id();
            // Yang disebut penjual dan staf keuangan, misalnya CAIR-01K7...
            $tabel->string('kode', 31)->unique();
            $tabel->foreignId('pengaju_id')->constrained('pengguna')->restrictOnDelete();
            $tabel->unsignedBigInteger('jumlah_rupiah');
            $tabel->string('rekening_tujuan', 80);
            $tabel->string('status', 20)->index();
            // Selalu orang yang berbeda dari pengaju (LP-18).
            $tabel->foreignId('diputuskan_oleh')->nullable()->constrained('pengguna')->restrictOnDelete();
            $tabel->timestamp('diputuskan_pada')->nullable();
            $tabel->string('catatan', 200)->nullable();
            $tabel->timestamps();

            $tabel->index(['pengaju_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan');
    }
};

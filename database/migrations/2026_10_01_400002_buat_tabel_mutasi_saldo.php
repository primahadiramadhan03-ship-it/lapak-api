<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_saldo', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('dompet_id')->constrained('dompet')->restrictOnDelete();
            $tabel->uuid('kelompok')->index(); // Untuk mengelompokkan pasangan mutasi
            $tabel->string('jenis', 20);
            $tabel->bigInteger('jumlah_rupiah'); // Negatif = keluar, Positif = masuk
            $tabel->bigInteger('saldo_setelah');
            $tabel->string('rujukan', 60)->index(); // Misal: nomor pesanan
            $tabel->string('keterangan', 160)->nullable();
            $tabel->timestamp('created_at')->useCurrent(); // LP-13: TIDAK ADA updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_saldo');
    }
};

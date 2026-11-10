<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LP-7: satu checkout dibayar dengan satu pembayaran (1:1).
     * Tabel pesanan tidak menunjuk ke pembayaran; keduanya menunjuk ke checkout.
     */
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->foreignId('checkout_id')->unique()->constrained('checkout')->restrictOnDelete();
            $tabel->string('kode', 30)->unique();
            $tabel->unsignedBigInteger('jumlah_rupiah');
            $tabel->string('status', 20)->index();
            $tabel->timestamp('batas_bayar_pada');
            $tabel->timestamp('lunas_pada')->nullable();
            $tabel->string('id_peristiwa', 64)->nullable()->unique();
            $tabel->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};

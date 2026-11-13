<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table): void {
            $table->timestamp('dikemas_pada')->nullable()->after('dibayar_pada');
            $table->timestamp('dikirim_pada')->nullable()->after('dikemas_pada');
            $table->string('nomor_resi', 64)->nullable()->after('dikirim_pada');
            $table->timestamp('selesai_pada')->nullable()->after('nomor_resi');
            $table->string('dibatalkan_oleh', 20)->nullable()->after('alasan_batal');
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table): void {
            $table->dropColumn([
                'dikemas_pada',
                'dikirim_pada',
                'nomor_resi',
                'selesai_pada',
                'dibatalkan_oleh',
            ]);
        });
    }
};

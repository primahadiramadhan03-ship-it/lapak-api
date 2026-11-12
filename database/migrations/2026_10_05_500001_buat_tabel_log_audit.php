<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_audit', function (Blueprint $tabel) {
            $tabel->id();
            $tabel->string('peristiwa', 60)->index();
            $tabel->foreignId('pelaku_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $tabel->string('sasaran', 80)->nullable()->index();
            $tabel->json('rincian');
            $tabel->char('id_permintaan', 36)->nullable();
            $tabel->char('hash_sebelumnya', 64)->unique();
            $tabel->char('hash', 64)->unique();
            $tabel->dateTime('dicatat_pada');
        });

        Schema::create('rantai_audit', function (Blueprint $tabel) {
            $tabel->unsignedTinyInteger('id')->primary();
            $tabel->char('hash_terakhir', 64);
            $tabel->unsignedBigInteger('jumlah');
        });

        DB::table('rantai_audit')->insert([
            'id' => 1,
            'hash_terakhir' => str_repeat('0', 64),
            'jumlah' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('log_audit');
        Schema::dropIfExists('rantai_audit');
    }
};

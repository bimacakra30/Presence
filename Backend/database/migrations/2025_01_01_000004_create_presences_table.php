<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel untuk menyimpan data presensi karyawan (check-in/check-out)
     */
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->string('uid'); // UID karyawan (referensi ke employees.uid)
            $table->string('firestore_id')->nullable(); // ID untuk sync dengan Firestore
            $table->string('nama'); // Nama karyawan (denormalized untuk performa)
            $table->date('tanggal'); // Tanggal presensi
            $table->time('clock_in')->nullable(); // Waktu check-in
            $table->time('clock_out')->nullable(); // Waktu check-out
            $table->string('foto_clock_in')->nullable(); // Path foto saat check-in
            $table->string('public_id_clock_in')->nullable(); // Cloudinary public ID untuk foto check-in
            $table->string('foto_clock_out')->nullable(); // Path foto saat check-out
            $table->string('public_id_clock_out')->nullable(); // Cloudinary public ID untuk foto check-out
            $table->boolean('status')->nullable(); // Status presensi (1=hadir, 0=tidak hadir)
            $table->string('durasi_keterlambatan')->nullable(); // Durasi keterlambatan (format: HH:MM)
            $table->timestamps(); // created_at, updated_at
            
            // Indexes untuk performa
            $table->index('uid');
            $table->index('tanggal');
            $table->index('firestore_id');
            $table->index(['uid', 'tanggal']); // Composite index untuk query presensi per karyawan per tanggal
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
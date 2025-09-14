<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel untuk menyimpan data pengajuan izin karyawan
     */
    public function up(): void
    {
        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->string('tanggal_masuk')->nullable(); // Tanggal masuk kembali (untuk izin cuti)
            $table->string('nama_karyawan'); // Nama karyawan (denormalized untuk performa)
            $table->string('jenis_perizinan'); // Jenis izin (sakit, cuti, dinas, dll)
            $table->date('tanggal_mulai'); // Tanggal mulai izin
            $table->date('tanggal_selesai')->nullable(); // Tanggal selesai izin
            $table->string('deskripsi')->nullable(); // Deskripsi/alasan izin
            $table->string('bukti_izin')->nullable(); // Path file bukti izin
            $table->string('bukti_izin_public_id')->nullable(); // Cloudinary public ID untuk bukti izin
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // Status persetujuan
            $table->string('uid')->nullable(); // UID karyawan (referensi ke employees.uid)
            $table->string('firestore_id')->nullable(); // ID untuk sync dengan Firestore
            $table->timestamps(); // created_at, updated_at
            
            // Indexes untuk performa
            $table->index('uid');
            $table->index('status');
            $table->index('tanggal_mulai');
            $table->index('tanggal_selesai');
            $table->index('firestore_id');
            $table->index('jenis_perizinan');
            $table->index(['uid', 'status']); // Composite index untuk query izin per karyawan
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permits');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel untuk menyimpan data karyawan yang akan melakukan presensi
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('uid')->unique()->nullable(); // Unique ID untuk sync dengan mobile app
            $table->string('photo')->nullable(); // Foto profil karyawan
            $table->string('name'); // Nama lengkap karyawan
            $table->string('username')->nullable(); // Username untuk login
            $table->string('email')->unique(); // Email unik karyawan
            $table->string('password')->nullable(); // Password ter-hash
            $table->string('phone')->nullable(); // Nomor telepon
            $table->text('address')->nullable(); // Alamat lengkap
            $table->date('date_of_birth')->nullable(); // Tanggal lahir
            $table->string('position')->nullable(); // Posisi/jabatan
            $table->string('provider')->nullable(); // Provider login (google, etc)
            $table->enum('status', ['aktif', 'non-aktif', 'terminated'])->default('aktif'); // Status karyawan
            $table->string('firestore_id')->nullable(); // ID untuk sync dengan Firestore
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at untuk soft delete
            
            // Indexes untuk performa
            $table->index('uid');
            $table->index('email');
            $table->index('status');
            $table->index('firestore_id');
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
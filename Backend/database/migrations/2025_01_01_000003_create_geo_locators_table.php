<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel untuk menyimpan data lokasi geografis yang valid untuk presensi
     */
    public function up(): void
    {
        Schema::create('geo_locators', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lokasi'); // Nama lokasi (contoh: Kantor Pusat, Cabang A)
            $table->string('deskripsi'); // Deskripsi lokasi
            $table->decimal('latitude', 11, 6); // Koordinat latitude
            $table->decimal('longitude', 11, 6); // Koordinat longitude
            $table->integer('radius'); // Radius dalam meter untuk validasi presensi
            $table->string('firestore_id')->nullable(); // ID untuk sync dengan Firestore
            $table->timestamps(); // created_at, updated_at
            
            // Indexes untuk performa
            $table->index('firestore_id');
            $table->index(['latitude', 'longitude']); // Composite index untuk query geografis
            $table->index('nama_lokasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_locators');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan field baru untuk informasi tambahan presensi:
     * - earlyClockOut: Boolean untuk menandai pulang lebih awal
     * - earlyClockOutReason: Alasan pulang lebih awal
     * - locationName: Nama lokasi presensi
     */
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->boolean('early_clock_out')->default(false)->after('status')->comment('Menandai apakah karyawan pulang lebih awal');
            $table->string('early_clock_out_reason')->nullable()->after('early_clock_out')->comment('Alasan pulang lebih awal');
            $table->string('location_name')->nullable()->after('early_clock_out_reason')->comment('Nama lokasi presensi');
            
            // Index untuk performa query
            $table->index('early_clock_out');
            $table->index('location_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['early_clock_out']);
            $table->dropIndex(['location_name']);
            
            // Drop columns
            $table->dropColumn([
                'early_clock_out',
                'early_clock_out_reason', 
                'location_name'
            ]);
        });
    }
};

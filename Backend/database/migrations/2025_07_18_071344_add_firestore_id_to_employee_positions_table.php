<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            $table->string('firestore_id')
                ->nullable()
                ->after('end_date')
                ->comment('Firestore document ID for the employee position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            //
        });
    }
};

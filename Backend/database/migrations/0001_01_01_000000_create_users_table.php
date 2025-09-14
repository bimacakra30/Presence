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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('photo')->nullable(); // Foto profil admin
            $table->string('name'); // Nama lengkap admin
            $table->string('email')->unique(); // Email unik untuk login
            $table->timestamp('email_verified_at')->nullable(); // Verifikasi email
            $table->string('password'); // Password ter-hash
            $table->enum('role', ['superadmin', 'admin'])->default('admin'); // Role admin
            $table->enum('status', ['active', 'inactive'])->default('active'); // Status akun
            $table->rememberToken(); // Token untuk remember me
            $table->timestamps(); // created_at, updated_at
            
            // Indexes untuk performa
            $table->index('email');
            $table->index('role');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

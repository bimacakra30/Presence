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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('type')->default('general'); // general, presence, permit, salary, announcement, system
            $table->json('data')->nullable(); // Additional data for mobile app
            $table->string('recipient_type'); // Employee, User, etc.
            $table->unsignedBigInteger('recipient_id');
            $table->string('fcm_token')->nullable(); // FCM token for push notification
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, scheduled
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('image_url')->nullable(); // URL untuk gambar notifikasi
            $table->string('action_url')->nullable(); // URL untuk action ketika notifikasi di-tap
            $table->timestamp('scheduled_at')->nullable(); // Untuk notifikasi terjadwal
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['recipient_type', 'recipient_id']);
            $table->index('status');
            $table->index('type');
            $table->index('priority');
            $table->index('scheduled_at');
            $table->index('read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

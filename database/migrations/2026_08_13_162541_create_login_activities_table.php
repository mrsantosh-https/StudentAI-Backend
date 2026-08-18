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
        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();

            // User who attempted/login
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Login information
            $table->dateTime('login_at')->nullable();
            $table->dateTime('logout_at')->nullable();

            // Request information
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // success / failed / blocked
            $table->string('status', 20)->default('success');

            // Optional reason for failed/blocked login
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            // Faster admin dashboard queries
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_activities');
    }
};
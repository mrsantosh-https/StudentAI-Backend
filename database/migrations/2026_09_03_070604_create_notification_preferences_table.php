<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('in_app_notifications')->default(true);
            $table->boolean('email_notifications')->default(true);

            $table->boolean('support_notifications')->default(true);
            $table->boolean('ai_notifications')->default(true);
            $table->boolean('job_notifications')->default(true);
            $table->boolean('marketing_notifications')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
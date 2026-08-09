<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deleted_accounts', function (Blueprint $table) {
            $table->id();

            // Original users table ID
            $table->unsignedBigInteger('original_user_id')->nullable();

            $table->string('name')->nullable();
            $table->string('email')->nullable();

            // Optional user/profile information
            $table->string('phone')->nullable();
            $table->string('profile_photo')->nullable();

            // Why/how account was deleted
            $table->string('deleted_by')->default('user');
            $table->text('reason')->nullable();

            // Additional non-sensitive info if required
            $table->json('metadata')->nullable();

            $table->timestamp('account_deleted_at');

            $table->timestamps();

            $table->index('original_user_id');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deleted_accounts');
    }
};
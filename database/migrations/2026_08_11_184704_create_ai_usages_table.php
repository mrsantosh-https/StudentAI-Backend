<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tool');

            $table->string('status')
                ->default('success');

            $table->unsignedInteger('prompt_tokens')
                ->default(0);

            $table->unsignedInteger('completion_tokens')
                ->default(0);

            $table->unsignedInteger('total_tokens')
                ->default(0);

            $table->text('error_message')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_interviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // NEW
            $table->uuid('interview_session_id');

            $table->string('role');
            $table->string('experience')->default('Fresher');

            $table->integer('question_no');

            $table->text('question');

            $table->longText('answer')->nullable();

            $table->longText('feedback')->nullable();

            $table->integer('score')->default(0);

            // NEW
            $table->boolean('is_completed')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_interviews');
    }
};
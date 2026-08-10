<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resume_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resume_id')
                ->constrained('resumes')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedInteger('version_number');

            $table->string('title')->nullable();
            $table->string('full_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('pincode')->nullable();

            $table->string('linkedin', 500)->nullable();
            $table->string('github', 500)->nullable();
            $table->string('portfolio', 500)->nullable();

            $table->longText('career_objective')->nullable();
            $table->longText('summary')->nullable();
            $table->longText('education')->nullable();
            $table->longText('skills')->nullable();
            $table->longText('projects')->nullable();
            $table->longText('experience')->nullable();

            $table->string('template')->default('modern');

            $table->integer('ats_score')->nullable();

            $table->timestamps();

            $table->unique([
                'resume_id',
                'version_number'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_versions');
    }
};
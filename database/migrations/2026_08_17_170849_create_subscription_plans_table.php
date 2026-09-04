<?php

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     public function up(): void
//     {
//         Schema::create('subscription_plans', function (Blueprint $table) {
//             $table->id();

//             $table->string('name');
//             $table->string('slug')->unique();
//             $table->text('description')->nullable();

//             $table->decimal('price', 10, 2)->default(0);
//             $table->string('billing_period')->default('monthly');

//             $table->unsignedInteger('resume_limit')->nullable();
//             $table->unsignedInteger('ai_usage_limit')->nullable();
//             $table->unsignedInteger('interview_limit')->nullable();
//             $table->unsignedInteger('job_tracker_limit')->nullable();

//             $table->boolean('is_active')->default(true);

//             $table->timestamps();
//         });
//     }

//     public function down(): void
//     {
//         Schema::dropIfExists('subscription_plans');
//     }
// };
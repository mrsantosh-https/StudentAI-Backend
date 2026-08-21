<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();

            $table->string('razorpay_order_id')
                ->unique();

            $table->string('razorpay_payment_id')
                ->nullable()
                ->unique();

            $table->string('razorpay_signature')
                ->nullable();

            $table->integer('amount');

            $table->string('currency', 10)
                ->default('INR');

            $table->string('status')
                ->default('created');

            $table->string('method')
                ->nullable();

            $table->text('failure_reason')
                ->nullable();

            $table->timestamp('paid_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
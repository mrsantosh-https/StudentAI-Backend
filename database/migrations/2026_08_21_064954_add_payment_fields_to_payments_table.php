<?php

// use Illuminate\Database\Migrations\Migration;
// use Illuminate\Database\Schema\Blueprint;
// use Illuminate\Support\Facades\Schema;

// return new class extends Migration
// {
//     public function up(): void
//     {
//         Schema::table('payments', function (Blueprint $table) {

//             $table->foreignId('user_id')
//                 ->after('id')
//                 ->constrained()
//                 ->cascadeOnDelete();

//             $table->foreignId('subscription_plan_id')
//                 ->after('user_id')
//                 ->constrained('subscription_plans')
//                 ->restrictOnDelete();

//             $table->string('razorpay_order_id')
//                 ->unique()
//                 ->after('subscription_plan_id');

//             $table->string('razorpay_payment_id')
//                 ->nullable()
//                 ->unique()
//                 ->after('razorpay_order_id');

//             $table->string('razorpay_signature')
//                 ->nullable()
//                 ->after('razorpay_payment_id');

//             $table->unsignedBigInteger('amount')
//                 ->after('razorpay_signature');

//             $table->string('currency', 3)
//                 ->default('INR')
//                 ->after('amount');

//             $table->string('status')
//                 ->default('created')
//                 ->after('currency');

//             $table->string('method')
//                 ->nullable()
//                 ->after('status');

//             $table->text('failure_reason')
//                 ->nullable()
//                 ->after('method');

//             $table->timestamp('paid_at')
//                 ->nullable()
//                 ->after('failure_reason');
//         });
//     }

//     public function down(): void
//     {
//         Schema::table('payments', function (Blueprint $table) {

//             $table->dropForeign(['user_id']);
//             $table->dropForeign(['subscription_plan_id']);

//             $table->dropColumn([
//                 'user_id',
//                 'subscription_plan_id',
//                 'razorpay_order_id',
//                 'razorpay_payment_id',
//                 'razorpay_signature',
//                 'amount',
//                 'currency',
//                 'status',
//                 'method',
//                 'failure_reason',
//                 'paid_at',
//             ]);
//         });
//     }
// };
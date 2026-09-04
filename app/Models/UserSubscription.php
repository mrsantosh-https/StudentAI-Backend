<?php

// namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;

// class UserSubscription extends Model
// {
//     use HasFactory;

//     protected $fillable = [
//         'user_id',
//         'subscription_plan_id',
//         'status',
//         'starts_at',
//         'ends_at',
//         'cancelled_at',
//     ];

//     protected $casts = [
//         'starts_at' => 'datetime',
//         'ends_at' => 'datetime',
//         'cancelled_at' => 'datetime',
//     ];

//     /**
//      * User
//      */
//     public function user(): BelongsTo
//     {
//         return $this->belongsTo(
//             User::class,
//             'user_id'
//         );
//     }

//     /**
//      * Subscription Plan
//      */
//     public function plan(): BelongsTo
//     {
//         return $this->belongsTo(
//             SubscriptionPlan::class,
//             'subscription_plan_id'
//         );
//     }

//     /**
//      * Check whether subscription is currently active.
//      */
//     public function isActive(): bool
//     {
//         if ($this->status !== 'active') {
//             return false;
//         }

//         if (
//             $this->ends_at &&
//             now()->greaterThanOrEqualTo($this->ends_at)
//         ) {
//             return false;
//         }

//         return true;
//     }
// }
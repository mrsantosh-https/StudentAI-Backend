<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_period',
        'resume_limit',
        'ai_usage_limit',
        'interview_limit',
        'job_tracker_limit',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'resume_limit' => 'integer',
        'ai_usage_limit' => 'integer',
        'interview_limit' => 'integer',
        'job_tracker_limit' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(
            UserSubscription::class,
            'subscription_plan_id'
        );
    }
}
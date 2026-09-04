<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'in_app_notifications',
        'email_notifications',
        'support_notifications',
        'ai_notifications',
        'job_notifications',
        'marketing_notifications',
    ];

    protected $casts = [
        'in_app_notifications' => 'boolean',
        'email_notifications' => 'boolean',
        'support_notifications' => 'boolean',
        'ai_notifications' => 'boolean',
        'job_notifications' => 'boolean',
        'marketing_notifications' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
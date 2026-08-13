<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AIUsage extends Model
{
    use HasFactory;

    protected $table = 'ai_usages';

    protected $fillable = [
        'user_id',
        'tool',
        'status',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'error_message',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
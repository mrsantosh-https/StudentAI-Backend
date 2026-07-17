<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChat extends Model
{
    protected $fillable = [
        'user_id',
        'question',
        'answer',
        'model',
        'liked',
        'disliked',
    ];

    public function user()
    {
        AiChat::create([
    'user_id' => $request->user()->id,
    'question' => $request->message,
    'answer' => $reply,
    'model' => 'groq-llama',
]);
        return $this->belongsTo(User::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
    ];

   public function store(Request $request)
{
    $record = MockInterview::create([
        'user_id' => auth()->id(),
        'role' => $request->role,
        'experience' => $request->experience,
        'question' => $request->question,
        'answer' => $request->answer,
        'feedback' => $request->feedback,
        'score' => $request->score,
    ]);

    if ($request->is_completed) {
        Notification::create([
            'user_id' => auth()->id(),
            'title' => 'Mock Interview Completed',
            'message' => 'Your mock interview result is ready.',
            'type' => 'success',
        ]);
    }

    return response()->json([
        'message' => 'Interview saved successfully',
        'data' => $record,
    ], 201);
}
}
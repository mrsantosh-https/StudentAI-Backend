<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewHistory extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'question',
        'answer',
        'feedback',
        'score',
    ];
}
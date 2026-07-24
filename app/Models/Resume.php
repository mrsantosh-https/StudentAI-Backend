<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'full_name',
        'email',
        'phone',
        'linkedin',
        'github',
        'portfolio',
        'summary',
        'education',
        'skills',
        'projects',
        'experience',
        'ats_score',
        'strengths',
        'weaknesses',
        'suggestions',
    ];

    protected $casts = [
     'ats_score' => 'integer',
     
    'strengths' => 'array',

    'weaknesses' => 'array',

    'suggestions' => 'array',

];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Resume extends Model
{   
    use HasFactory;
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
        'designation',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'template',
        'career_objective',
    ];

    protected $casts = [
     'ats_score' => 'integer',
     
    'strengths' => 'array',

    'weaknesses' => 'array',

    'suggestions' => 'array',

];

  public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function versions()
    {
        return $this->hasMany(ResumeVersion::class);
    }
}

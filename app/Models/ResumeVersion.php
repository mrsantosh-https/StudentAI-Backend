<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ResumeVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'resume_id',
        'user_id',
        'version_number',
        'title',
        'full_name',
        'designation',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'linkedin',
        'github',
        'portfolio',
        'career_objective',
        'summary',
        'education',
        'skills',
        'projects',
        'experience',
        'template',
        'ats_score',
    ];

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    protected $fillable = [
        'user_id',
        'company',
        'role',
        'status',
        'location',
        'job_link',
        'applied_date',
        'notes',
    ];
}
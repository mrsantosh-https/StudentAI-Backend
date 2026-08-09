<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedAccount extends Model
{
    protected $fillable = [
        'original_user_id',
        'name',
        'email',
        'phone',
        'profile_photo',
        'deleted_by',
        'reason',
        'metadata',
        'account_deleted_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'account_deleted_at' => 'datetime',
    ];
}
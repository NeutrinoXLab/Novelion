<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $fillable = [
        'session_id',
        'ip_address',
        'url',
        'referrer',
        'user_agent',
    ];
}
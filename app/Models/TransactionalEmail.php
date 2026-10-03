<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionalEmail extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'next_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}

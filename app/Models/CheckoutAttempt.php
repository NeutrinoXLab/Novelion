<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'retired_at' => 'datetime',
        'stripe_started_at' => 'datetime',
        'stripe_parameters' => 'array',
    ];
}

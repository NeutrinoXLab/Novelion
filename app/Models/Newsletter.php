<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    protected $fillable = [
        'email',
        'subscribed_at',
        'unsubscribed_at',
        'confirmation_token',
        'confirmation_sent_at',
        'confirmed_at',
        'consent_ip',
        'consent_user_agent',
        'unsubscribe_token',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime', 'unsubscribed_at' => 'datetime',
        'confirmation_sent_at' => 'datetime', 'confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Newsletter $newsletter) {
            if (empty($newsletter->unsubscribe_token)) {
                $newsletter->unsubscribe_token = Str::random(64);
            }
        });
    }
}

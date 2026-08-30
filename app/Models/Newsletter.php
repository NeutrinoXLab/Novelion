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
        'unsubscribe_token',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    protected $fillable = [
        'subject',
        'title',
        'content',
        'status',
        'recipients_count',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

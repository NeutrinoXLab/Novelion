<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_type',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Conversația din care face parte mesajul.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            ChatConversation::class,
            'conversation_id'
        );
    }

    /**
     * Verifică dacă mesajul a fost trimis de client.
     */
    public function isFromCustomer(): bool
    {
        return $this->sender_type === 'customer';
    }

    /**
     * Verifică dacă mesajul a fost trimis de administrator.
     */
    public function isFromAdmin(): bool
    {
        return $this->sender_type === 'admin';
    }
}
<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public string $state, public ?string $amount = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Starea rambursării pentru comanda '.$this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.refund-status');
    }
}

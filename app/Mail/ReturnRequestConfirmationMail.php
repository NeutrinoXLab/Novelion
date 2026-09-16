<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReturnRequestConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirmarea solicitării #'.$this->returnRequest->id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.return-request-confirmation');
    }
}

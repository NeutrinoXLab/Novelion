<?php

namespace App\Mail;

use App\Models\Newsletter;
use App\Models\NewsletterCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public NewsletterCampaign $campaign;

    public Newsletter $newsletter;

    public string $unsubscribeUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        NewsletterCampaign $campaign,
        Newsletter $newsletter
    ) {
        $this->campaign = $campaign;
        $this->newsletter = $newsletter;

        $this->unsubscribeUrl = route('newsletter.unsubscribe', [
            'token' => $newsletter->unsubscribe_token,
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}

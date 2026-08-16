<?php

namespace App\Mail;

use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Comanda ta a fost confirmată - ' . $this->order->order_number,
        );
    }

    /**
     * Email view.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-paid',
        );
    }

    /**
     * PDF attachment.
     */
    public function attachments(): array
    {
        return [

            Attachment::fromData(

                fn () => app(InvoiceService::class)
                    ->generate($this->order)
                    ->output(),

                'Factura-' . $this->order->order_number . '.pdf'

            )->withMime('application/pdf'),

        ];
    }
}
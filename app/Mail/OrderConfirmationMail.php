<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Order confirmation email sent once, right after the order transaction
 * commits successfully. Failed checkouts never produce an email.
 */
class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Order Confirmation — #' . $this->order->order_number);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-confirmation');
    }
}

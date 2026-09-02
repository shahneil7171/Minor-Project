<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Shipping notification sent to the customer when an admin transitions an
 * order to the "shipped" status. Only sent on a REAL transition — re-saving
 * the same status never triggers this email.
 */
class OrderShippedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public Carbon $shippedAt)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your KDP MART Order #' . $this->order->order_number . ' Has Shipped');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-shipped');
    }
}

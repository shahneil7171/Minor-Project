<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Approval notification sent to the buyer when an admin approves their
 * order. Only sent on the real pending -> approved transition — re-approval
 * is impossible, so this email can never duplicate.
 */
class OrderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your KDP MART Order #' . $this->order->order_number . ' Has Been Approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-approved');
    }
}

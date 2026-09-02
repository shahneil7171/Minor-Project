<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Approval notification sent to a SELLER whose products are in an approved
 * order. Receives only the lines that belong to this seller — never another
 * seller's items, payment credentials or admin-only information.
 */
class SellerOrderApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, OrderItem>  $items  the seller's own order lines
     */
    public function __construct(public Order $order, public array $items)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Approved Order #' . $this->order->order_number . ' — KDP MART');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-order-approved');
    }
}

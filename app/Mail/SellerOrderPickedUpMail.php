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
 * Pickup notification sent to a SELLER when the delivery partner collects
 * their part of an order. Receives only the seller's own lines.
 */
class SellerOrderPickedUpMail extends Mailable
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
        return new Envelope(subject: 'Order #' . $this->order->order_number . ' Picked Up — KDP MART');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-order-picked-up');
    }
}

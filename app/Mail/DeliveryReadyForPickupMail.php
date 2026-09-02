<?php

namespace App\Mail;

use App\Models\OrderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pickup-readiness notification sent to the delivery partner when the seller
 * marks the order packed ("ready for pickup"). Fires only on the assigned ->
 * ready_for_pickup transition, so it is never duplicated.
 */
class DeliveryReadyForPickupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public OrderDelivery $delivery)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Order #' . $this->delivery->order->order_number . ' Is Ready for Pickup — KDP MART');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.delivery-ready-pickup');
    }
}

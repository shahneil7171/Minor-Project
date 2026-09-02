<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Assignment notification sent to the delivery partner when an admin assigns
 * (or reassigns) an order to them. Sent once per real assignment — refreshing
 * pages never re-sends it.
 */
class DeliveryAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public OrderDelivery $delivery,
        public Order $order,
        public bool $isReassignment = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $prefix = $this->isReassignment ? 'Delivery Reassigned To You' : 'New Delivery Assigned';

        return new Envelope(subject: $prefix . ' — Order #' . $this->order->order_number . ' (KDP MART)');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.delivery-assigned');
    }
}

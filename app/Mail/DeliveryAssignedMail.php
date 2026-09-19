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

        return new Envelope(subject: $prefix . ' - Order #' . $this->order->order_number);
    }

    public function content(): Content
    {
        // The delivery partner's own name, the item count, the total and the
        // pickup seller(s) are resolved here so the email template only has to
        // render plain values (and never touches seller financial data).
        return new Content(view: 'emails.delivery-assigned', with: [
            'partnerName' => $this->delivery->deliveryPartner?->name,
            'itemsCount'  => (int) $this->order->items->sum('quantity'),
            'sellerNames' => $this->order->items
                ->map(fn ($item) => $item->seller?->name)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);
    }
}

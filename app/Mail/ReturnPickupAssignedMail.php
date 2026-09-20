<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Pickup assignment sent to the delivery partner after an admin schedules the
 * collection of a returned parcel. Mirrors DeliveryAssignedMail — the partner
 * is only ever sent the information needed to collect the parcel (never any
 * seller financial data).
 */
class ReturnPickupAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Return Pickup Assigned - Order #' . $this->returnRequest->order_number
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return-pickup-assigned',
            with: ['returnRequest' => $this->returnRequest]
        );
    }
}
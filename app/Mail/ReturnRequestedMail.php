<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer the moment their return request is stored.
 */
class ReturnRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Return Request Submitted - Order #' . $this->returnRequest->order_number
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return-requested',
            with: ['returnRequest' => $this->returnRequest]
        );
    }
}
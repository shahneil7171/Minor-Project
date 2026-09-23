<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer when their refund moves to "processing".
 *
 * Status tracking only — the demo integrates no payment-gateway refund API,
 * so the email states the refund is being processed/recorded by the store
 * rather than claiming a bank transfer already happened.
 */
class RefundProcessingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Refund Processing - Order #' . $this->returnRequest->order_number
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-processing',
            with: ['returnRequest' => $this->returnRequest]
        );
    }
}

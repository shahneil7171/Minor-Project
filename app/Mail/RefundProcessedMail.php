<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer once the refund has been marked as completed by an admin.
 *
 * The project tracks refund status only — no payment gateway refund API is
 * integrated, so this email states that the refund was completed/recorded by
 * the store rather than claiming a bank transfer happened automatically.
 */
class RefundProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Refund Completed - Order #' . $this->returnRequest->order_number
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.refund-processed',
            with: ['returnRequest' => $this->returnRequest]
        );
    }
}
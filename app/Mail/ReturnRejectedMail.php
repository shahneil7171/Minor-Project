<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer when an admin rejects their return request. The reason is
 * always included — a request is never rejected silently.
 */
class ReturnRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReturnRequest $returnRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Return Request Rejected - Order #' . $this->returnRequest->order_number
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.return-rejected',
            with: ['returnRequest' => $this->returnRequest]
        );
    }
}
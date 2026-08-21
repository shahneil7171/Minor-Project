<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $newsletterSubject,
        public string $newsletterBody,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->newsletterSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.newsletter');
    }
}

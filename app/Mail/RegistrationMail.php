<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email sent after a customer account is created.
 *
 * Never contains the password or any other credential — the customer chose
 * those themselves and they are never echoed back.
 */
class RegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Welcome to KDP MART!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration');
    }
}

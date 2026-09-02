<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Security notification sent after a password change succeeds — whether the
 * change came from the profile page or from completing a password reset.
 *
 * SECURITY: this email NEVER contains the new password, the old password or
 * any password hash. It only confirms the change happened.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Carbon $changedAt)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your KDP MART Password Was Changed');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-changed');
    }
}

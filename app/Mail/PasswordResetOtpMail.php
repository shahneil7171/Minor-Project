<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(protected string $otp, protected int $expiresInMinutes)
    {
    }

    public function build(): self
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Password Reset OTP')
            ->text('emails.password-reset-otp')
            ->with([
                'otp' => $this->otp,
                'expiresInMinutes' => $this->expiresInMinutes,
            ]);
    }
}

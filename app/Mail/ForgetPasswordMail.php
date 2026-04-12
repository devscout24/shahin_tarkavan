<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $otp;
    public string $expiresAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->otp = (string) $user->otp;
        $this->expiresAt = $user->otp_expires_at
            ? $user->otp_expires_at->format('F d, Y h:i A')
            : 'soon';
    }

    public function build()
    {
        return $this->subject('Forgot Password OTP')
            ->view('emails.forget-password');
    }
}

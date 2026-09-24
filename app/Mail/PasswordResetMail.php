<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The password-reset email. Queued through the `notifications` queue so
 * an SMTP outage doesn't block the login request's response.
 *
 * The reset link uses APP_URL + /reset-password?token=X&email=Y. The
 * front-end's reset page parses both and POSTs to /api/auth/reset-password.
 */
class PasswordResetMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $resetToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your Rocket Coding password',
        );
    }

    public function content(): Content
    {
        $resetUrl = sprintf(
            '%s/reset-password?email=%s&token=%s',
            rtrim((string) config('app.url'), '/'),
            urlencode($this->user->email),
            urlencode($this->resetToken),
        );

        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $resetUrl,
                'ttlMinutes' => (int) config('rocket_auth.password_reset_token_ttl_minutes', 60),
            ],
        );
    }
}

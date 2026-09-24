<?php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\Auth\PasswordResetRequested;
use App\Mail\PasswordResetMail;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the password-reset email on the `notifications` queue. Lives in
 * the Step 6b batch because the Mailable itself (and the queue it rides
 * on) didn't exist in Step 4.
 *
 * Auto-discovered via handle* naming. Registered implicitly.
 */
class QueuePasswordResetEmail
{
    public function handlePasswordResetRequested(PasswordResetRequested $event): void
    {
        Mail::to($event->user->email)->queue(
            (new PasswordResetMail(
                user: $event->user,
                resetToken: $event->resetToken,
            ))->onQueue('notifications'),
        );
    }
}

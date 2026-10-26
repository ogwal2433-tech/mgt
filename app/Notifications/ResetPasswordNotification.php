<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued password-reset email: the request never waits on SMTP and a
 * transport hiccup can never turn the page into a 500.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Reset your password | ' . config('app.name'))
            ->greeting('Hello ' . ($notifiable->name ?? 'there'))
            ->line('You requested a password reset for your account.')
            ->action('Reset Password', $url)
            ->line('This link expires in ' . config('auth.passwords.users.expire', 60) . ' minutes and can be used once.')
            ->line('If you did not request this, you can safely ignore this email.');
    }
}

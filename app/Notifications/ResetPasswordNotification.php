<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Build the branded Biiteek password-reset mail message.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage())
            ->subject('Reset your Biiteek password')
            ->view('emails.reset-password', [
                'name' => $notifiable->employee?->full_name ?? $notifiable->name,
                'actionUrl' => $url,
                'expiresIn' => config('auth.passwords.users.expire', 60),
                'company' => \App\Support\Settings::company(),
            ]);
    }
}

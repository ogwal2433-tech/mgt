<?php

namespace App\Support;

use App\Models\EmailDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\GenericEmailNotification;
use Illuminate\Support\Facades\Mail;

/**
 * Central notification hub: in-app rows always, email when configured.
 */
final class NotificationService
{
    public static function notify(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        array $data = [],
        bool $sendEmail = true,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'data' => $data ?: null,
            'channel' => 'in_app',
        ]);

        if ($sendEmail) {
            self::email($user, $title, $message, $type, $data);
        }

        return $notification;
    }

    /** @param iterable<User> $users */
    public static function notifyMany(iterable $users, string $type, string $title, string $message, ?string $link = null, array $data = [], bool $sendEmail = true): void
    {
        foreach ($users as $user) {
            self::notify($user, $type, $title, $message, $link, $data, $sendEmail);
        }
    }

    public static function email(User $user, string $subject, string $message, string $type = 'generic', array $data = [], ?string $linkedType = null, ?int $linkedId = null): EmailDelivery
    {
        $delivery = EmailDelivery::create([
            'user_id' => $user->id,
            'to_email' => $user->email,
            'subject' => $subject,
            'type' => $type,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'status' => 'pending',
        ]);

        try {
            Mail::to($user->email)->send(new GenericEmailNotification($subject, $message, $data));
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 1000),
                'retries' => $delivery->retries + 1,
            ]);
        }

        return $delivery;
    }

    /**
     * Deliver a dedicated mailable (e.g. the branded invite) and track its delivery status.
     */
    public static function sendMailable(User $user, \Illuminate\Mail\Mailable $mailable, string $subject, string $type = 'generic', ?string $linkedType = null, ?int $linkedId = null): EmailDelivery
    {
        $delivery = EmailDelivery::create([
            'user_id' => $user->id,
            'to_email' => $user->email,
            'subject' => $subject,
            'type' => $type,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'status' => 'pending',
        ]);

        try {
            Mail::to($user->email)->send($mailable);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 1000),
                'retries' => $delivery->retries + 1,
            ]);
        }

        return $delivery;
    }

    /**
     * Deliver a dedicated mailable to any email address (e.g. guest meeting
     * invites) and track its delivery status.
     */
    public static function sendEmailTo(string $email, \Illuminate\Mail\Mailable $mailable, string $subject, string $type = 'generic', ?string $linkedType = null, ?int $linkedId = null): EmailDelivery
    {
        $delivery = EmailDelivery::create([
            'user_id' => null,
            'to_email' => $email,
            'subject' => $subject,
            'type' => $type,
            'linked_type' => $linkedType,
            'linked_id' => $linkedId,
            'status' => 'pending',
        ]);

        try {
            Mail::to($email)->send($mailable);
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 1000),
                'retries' => $delivery->retries + 1,
            ]);
        }

        return $delivery;
    }

    public static function retry(EmailDelivery $delivery): EmailDelivery
    {
        if (! $delivery->user) {
            return $delivery;
        }

        try {
            Mail::to($delivery->to_email)->send(new GenericEmailNotification($delivery->subject, $delivery->subject, []));
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error' => substr($e->getMessage(), 0, 1000),
                'retries' => $delivery->retries + 1,
            ]);
        }

        return $delivery;
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MeetingInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $meetingTitle,
        public string $hostName,
        public string $joinUrl,
        public string $expiresAt,
        public ?string $scheduledAt = null,
        public string $accessLabel = 'Anyone with the link can join',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation: ' . $this->meetingTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.meeting-invite', with: [
            'company' => \App\Support\Settings::company(),
            'meetingTitle' => $this->meetingTitle,
            'hostName' => $this->hostName,
            'joinUrl' => $this->joinUrl,
            'expiresAt' => $this->expiresAt,
            'scheduledAt' => $this->scheduledAt,
            'accessLabel' => $this->accessLabel,
        ]);
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $assignmentTitle,
        public string $body,
        public string $link,
        public string $dueAt,
        public string $recipientName = '',
        public bool $isCc = false,
        public ?string $referenceNumber = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->isCc ? 'CC: ' : '') . 'Assignment: ' . $this->assignmentTitle);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.assignment-invite', with: [
            'company' => \App\Support\Settings::company(),
            'assignmentTitle' => $this->assignmentTitle,
            'body' => $this->body,
            'link' => $this->link,
            'dueAt' => $this->dueAt,
            'recipientName' => $this->recipientName,
            'isCc' => $this->isCc,
            'referenceNumber' => $this->referenceNumber,
        ]);
    }
}

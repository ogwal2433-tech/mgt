<?php

namespace App\Mail;

use App\Models\ExternalRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalShareMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ExternalRequisition $requisition,
        public string $link,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Action required: fill & sign requisition form {$this->requisition->requisition_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.external-share',
            with: [
                'name' => $this->requisition->external_contact ?? 'there',
                'requisition' => $this->requisition,
                'link' => $this->link,
                'company' => \App\Support\Settings::company(),
            ],
        );
    }
}

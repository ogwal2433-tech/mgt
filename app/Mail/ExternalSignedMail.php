<?php

namespace App\Mail;

use App\Models\ExternalRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ExternalRequisition $requisition,
        public string $pdfBinary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Requisition form {$this->requisition->requisition_number} — signed copy",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.external-signed',
            with: [
                'name' => $this->requisition->signed_by_name ?? $this->requisition->external_contact ?? 'there',
                'requisition' => $this->requisition,
                'company' => \App\Support\Settings::company(),
            ],
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(
                fn () => $this->pdfBinary,
                "requisition-{$this->requisition->requisition_number}-signed.pdf"
            )->as("requisition-{$this->requisition->requisition_number}-signed.pdf")
                ->withMime('application/pdf'),
        ];
    }
}

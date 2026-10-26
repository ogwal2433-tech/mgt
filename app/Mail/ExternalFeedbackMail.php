<?php

namespace App\Mail;

use App\Models\ExternalRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalFeedbackMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ExternalRequisition $requisition) {}

    public function envelope(): Envelope
    {
        $outcome = $this->requisition->status === 'approved' ? 'Approved' : 'Update';

        return new Envelope(
            subject: "Requisition {$this->requisition->requisition_number} — {$outcome} (Biiteek)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.external-feedback',
            with: [
                'name' => $this->requisition->signed_by_name ?? $this->requisition->external_contact ?? 'there',
                'requisition' => $this->requisition,
                'company' => \App\Support\Settings::company(),
            ],
        );
    }
}

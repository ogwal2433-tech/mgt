<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalRequisition extends Model
{
    protected $appends = ['pdf_url'];

    protected $fillable = [
        'requisition_number', 'date', 'external_party_id', 'external_party_name',
        'external_contact', 'external_email', 'external_phone', 'prepared_by_user_id',
        'project_id', 'client_reference', 'purpose', 'currency', 'total_amount',
        'required_delivery_date', 'status', 'is_approved', 'approved_by_user_id',
        'approved_at', 'terms', 'notes',
        'payee_name', 'requested_by_name', 'department_text', 'mode_of_payment',
        'date_of_activity', 'activity_budget', 'share_token', 'token_expires_at',
        'sent_to_external_at', 'signed_by_name', 'signed_by_email',
        'signature_document_id', 'signed_at', 'actual_total', 'unspent_funds', 'approval_feedback', 'feedback_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'required_delivery_date' => 'date',
            'date_of_activity' => 'date',
            'total_amount' => 'decimal:2',
            'activity_budget' => 'decimal:2',
            'actual_total' => 'decimal:2',
            'unspent_funds' => 'decimal:2',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'sent_to_external_at' => 'datetime',
            'signed_at' => 'datetime',
            'feedback_sent_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(ExternalParty::class, 'external_party_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExternalRequisitionItem::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExternalRequisitionAttachment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ExternalRequisitionApproval::class, 'external_requisition_id')->orderBy('id');
    }

    public function signature(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'signature_document_id');
    }

    public function isTokenValid(): bool
    {
        return $this->share_token !== null
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }

    /**
     * Tokenized PDF link (signed + expiring).
     */
    public function getPdfUrlAttribute(): string
    {
        return \App\Support\Links::externalPdf($this);
    }
}
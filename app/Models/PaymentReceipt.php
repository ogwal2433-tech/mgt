<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    protected $appends = ['pdf_url'];

    protected $fillable = [
        'receipt_number', 'payment_id', 'requisition_id', 'amount', 'currency',
        'remaining_balance', 'requester_user_id', 'payee_name', 'purpose',
        'payment_method', 'payment_reference', 'payment_date', 'generated_by_user_id',
        'generated_at', 'document_id', 'delivery_status', 'delivered_at',
        'delivery_error', 'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'payment_date' => 'date',
            'generated_at' => 'datetime',
            'delivered_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    /**
     * Tokenized PDF link (signed + expiring).
     */
    public function getPdfUrlAttribute(): string
    {
        return \App\Support\Links::receiptPdf($this);
    }
}
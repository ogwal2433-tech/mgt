<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $appends = ['pdf_url'];

    protected $fillable = [
        'invoice_number', 'external_party_id', 'project_id', 'client_name',
        'client_contact', 'client_email', 'client_address', 'issue_date',
        'due_date', 'status', 'currency', 'subtotal', 'tax_rate', 'tax_amount',
        'total_amount', 'amount_paid', 'amount_due', 'terms', 'notes', 'sent_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(ExternalParty::class, 'external_party_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('id');
    }

    public function isSettled(): bool
    {
        return Money::compare((string) $this->amount_due, '0.00') <= 0;
    }

    /**
     * Positive = days overdue, negative = days until due.
     */
    public function daysOverdue(): int
    {
        return (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay(), false);
    }

    public function agingBucket(): string
    {
        if ($this->isSettled()) {
            return 'paid';
        }

        $overdue = $this->daysOverdue();

        return match (true) {
            $overdue <= 0 => 'current',
            $overdue <= 30 => '1_30',
            $overdue <= 60 => '31_60',
            $overdue <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /**
     * Tokenized PDF link (signed + expiring).
     */
    public function getPdfUrlAttribute(): string
    {
        return \App\Support\Links::invoicePdf($this);
    }
}
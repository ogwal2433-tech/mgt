<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountabilityItem extends Model
{
    protected $fillable = ['accountability_submission_id', 'description', 'amount', 'expense_date', 'receipt_document_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AccountabilitySubmission::class, 'accountability_submission_id');
    }

    public function receiptDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'receipt_document_id');
    }
}

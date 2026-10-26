<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'requisition_id', 'accountability_submission_id', 'amount', 'currency',
        'refund_date', 'method', 'reference', 'recorded_by_user_id', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'refund_date' => 'date'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AccountabilitySubmission::class, 'accountability_submission_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}

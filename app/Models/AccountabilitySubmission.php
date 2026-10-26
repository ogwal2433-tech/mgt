<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountabilitySubmission extends Model
{
    protected $fillable = [
        'requisition_id', 'submitted_by_user_id', 'advance_amount', 'actual_amount',
        'unspent_balance', 'refund_amount', 'explanation', 'status',
        'reviewed_by_user_id', 'reviewed_at', 'review_comments', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'advance_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'unspent_balance' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountabilityItem::class);
    }
}

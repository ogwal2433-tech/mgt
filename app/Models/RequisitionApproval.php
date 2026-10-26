<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionApproval extends Model
{
    protected $fillable = [
        'requisition_id', 'stage', 'action', 'user_id', 'employee_id', 'decision_amount',
        'reason', 'comments', 'previous_status', 'new_status', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decision_amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

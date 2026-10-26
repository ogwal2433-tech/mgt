<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalRequisitionApproval extends Model
{
    protected $fillable = [
        'external_requisition_id', 'stage', 'action', 'user_id',
        'comments', 'previous_status', 'new_status', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ExternalRequisition::class, 'external_requisition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

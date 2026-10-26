<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalRequisitionItem extends Model
{
    protected $fillable = ['external_requisition_id', 'description', 'quantity', 'unit', 'unit_price', 'total_price', 'actual_cost', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ExternalRequisition::class, 'external_requisition_id');
    }
}

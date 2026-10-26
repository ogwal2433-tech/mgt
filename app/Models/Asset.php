<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'asset_tag', 'name', 'category', 'serial_number', 'purchase_date', 'purchase_cost',
        'warranty_end', 'condition', 'location', 'status', 'assigned_to_employee_id',
        'purchase_requisition_id', 'maintenance_history', 'disposed_at', 'disposal_notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_end' => 'date',
            'purchase_cost' => 'decimal:2',
            'disposed_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to_employee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }
}

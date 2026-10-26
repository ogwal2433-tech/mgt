<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $fillable = [
        'code', 'name', 'department_id', 'project_id', 'fiscal_year',
        'total_amount', 'spent_amount', 'currency', 'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'fiscal_year' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }
}

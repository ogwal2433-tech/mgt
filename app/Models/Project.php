<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'project_code', 'name', 'description', 'client_name', 'is_internal',
        'department_id', 'lead_employee_id', 'start_date', 'target_completion_date',
        'actual_completion_date', 'budget', 'currency', 'status', 'progress_percentage',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_completion_date' => 'date',
            'actual_completion_date' => 'date',
            'budget' => 'decimal:2',
            'is_internal' => 'boolean',
            'progress_percentage' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'lead_employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class);
    }
}

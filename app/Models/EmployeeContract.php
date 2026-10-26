<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeContract extends Model
{
    protected $fillable = [
        'contract_number', 'employee_id', 'contract_type', 'job_title', 'department_id',
        'start_date', 'end_date', 'probation_end_date', 'renewal_date', 'status',
        'salary', 'currency', 'working_hours', 'signature_status', 'current_version',
        'notes', 'is_confidential', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'renewal_date' => 'date',
            'salary' => 'decimal:2',
            'is_confidential' => 'boolean',
            'current_version' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class, 'contract_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ContractReminder::class, 'contract_id');
    }

    public function scopeExpiringSoon($query, int $days = 90)
    {
        return $query->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays($days)->toDateString())
            ->whereIn('status', ['active', 'expiring']);
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->end_date?->diffInDays(now(), false) * -1;
    }
}

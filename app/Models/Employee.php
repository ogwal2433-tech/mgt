<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'employee_code', 'first_name', 'last_name', 'other_names', 'email', 'phone',
        'department_id', 'position_id', 'supervisor_id', 'employment_status',
        'employment_type', 'hire_date', 'exit_date', 'gender', 'date_of_birth',
        'national_id', 'address', 'emergency_contact_name', 'emergency_contact_phone',
        'job_title', 'basic_salary', 'currency', 'notes', 'is_active',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'exit_date' => 'date',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'basic_salary' => 'decimal:2',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim((string) preg_replace('/\s+/', ' ', "{$this->first_name} {$this->other_names} {$this->last_name}"));
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function projectsLed(): HasMany
    {
        return $this->hasMany(Project::class, 'lead_employee_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to_employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('employment_status', '!=', 'terminated');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('other_names', 'like', "%{$term}%")
                ->orWhere('employee_code', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('job_title', 'like', "%{$term}%");
        });
    }
}

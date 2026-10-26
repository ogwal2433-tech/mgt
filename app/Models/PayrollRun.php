<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'run_number', 'pay_period_id', 'status', 'total_basic', 'total_gross',
        'total_deductions', 'total_net', 'total_paid', 'computed_by', 'computed_at',
        'approved_by', 'approved_at', 'paid_at', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_basic' => 'decimal:2',
            'total_gross' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'total_net' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'computed_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayPeriod::class, 'pay_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollPayment::class);
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'closed'], true);
    }
}

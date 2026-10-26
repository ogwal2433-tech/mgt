<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollLine extends Model
{
    protected $appends = ['payslip_url'];

    protected $fillable = [
        'payroll_run_id', 'employee_id', 'employee_code', 'employee_name',
        'basic_salary', 'gross_amount', 'tax_amount', 'advance_amount',
        'statutory_amount', 'other_deduction_amount', 'total_deductions',
        'net_amount', 'breakdown', 'status', 'payslip_number',
        'payslip_document_id', 'payslip_generated_at', 'payslip_delivery_status',
        'payslip_emailed_at', 'payslip_email_error',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'statutory_amount' => 'decimal:2',
            'other_deduction_amount' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'breakdown' => 'array',
            'payslip_generated_at' => 'datetime',
            'payslip_emailed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollLineItem::class, 'payroll_line_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(PayrollPayment::class, 'payroll_line_id');
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'payslip_document_id');
    }

    /**
     * Tokenized PDF link (signed + expiring).
     */
    public function getPayslipUrlAttribute(): string
    {
        return \App\Support\Links::payslipPdf($this);
    }
}
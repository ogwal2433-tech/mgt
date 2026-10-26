<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    protected $fillable = [
        'requisition_number', 'requester_user_id', 'requester_employee_id', 'department_id',
        'category_id', 'project_id', 'title', 'purpose', 'justification', 'currency',
        'requested_amount', 'approved_amount', 'amount_paid', 'amount_accounted',
        'amount_refunded', 'priority', 'required_date', 'status', 'current_stage',
        'approval_chain', 'supplier_name', 'supplier_contact', 'budget_reference', 'is_advance',
        'accountability_required', 'submitted_at', 'closed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'approval_chain' => 'array',
            'requested_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_accounted' => 'decimal:2',
            'amount_refunded' => 'decimal:2',
            'is_advance' => 'boolean',
            'accountability_required' => 'boolean',
            'required_date' => 'date',
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function requesterEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RequisitionCategory::class, 'category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class)->orderBy('sort_order');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequisitionAttachment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequisitionApproval::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->orderBy('id');
    }

    public function accountabilitySubmissions(): HasMany
    {
        return $this->hasMany(AccountabilitySubmission::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function remainingApprovedAmount(): string
    {
        $approved = (string) ($this->approved_amount ?? '0.00');
        $paid = (string) $this->amount_paid;

        return \App\Support\Money::sub($approved, $paid);
    }

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->hasPermission(\App\Support\Permissions::REQUISITIONS_VIEW_ALL)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('requester_user_id', $user->id);

            if ($user->employee?->department_id && $user->hasPermission(\App\Support\Permissions::REQUISITIONS_VIEW_DEPARTMENT)) {
                $q->orWhere('department_id', $user->employee->department_id);
            }
        });
    }
}

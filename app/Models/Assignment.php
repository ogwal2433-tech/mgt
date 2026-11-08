<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'reference_number', 'title', 'body', 'require_deliverables', 'created_by',
        'due_at', 'status', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'require_deliverables' => 'boolean',
            'due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AssignmentRecipient::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(AssignmentPart::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class);
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed() && ! $this->isPastDue();
    }
}

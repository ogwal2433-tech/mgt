<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSubmission extends Model
{
    protected $fillable = [
        'reference_number', 'user_id', 'employee_id', 'recipient_id', 'title', 'category',
        'notes', 'status', 'reviewed_by', 'reviewed_at', 'review_comments',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The person the submission is addressed to (HR, MD, Lead, ...).
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentSubmissionFile::class, 'document_submission_id');
    }
}

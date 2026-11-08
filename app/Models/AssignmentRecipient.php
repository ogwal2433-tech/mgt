<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentRecipient extends Model
{
    public const KIND_TO = 'to';
    public const KIND_CC = 'cc';

    protected $fillable = [
        'assignment_id', 'kind', 'email', 'name', 'user_id',
        'token', 'sent_at', 'viewed_at', 'submitted_at',
        'signer_name', 'signature_path', 'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(AssignmentDeliverable::class);
    }
}

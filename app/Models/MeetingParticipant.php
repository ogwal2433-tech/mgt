<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    public const ROLE_HOST = 'host';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_PARTICIPANT = 'participant';

    protected $fillable = [
        'meeting_id', 'user_id', 'identity', 'display_name', 'role',
        'joined_at', 'left_at', 'removed_at', 'removed_by',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }
}

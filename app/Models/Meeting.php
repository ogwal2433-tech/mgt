<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Meeting extends Model
{
    public const MODE_GUEST = 'guest';
    public const MODE_STAFF = 'staff_only';

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_LIVE = 'live';
    public const STATUS_ENDED = 'ended';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'public_id', 'title', 'host_user_id', 'access_mode', 'show_host_role',
        'scheduled_at', 'link_expires_at', 'status', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'show_host_role' => 'boolean',
            'scheduled_at' => 'datetime',
            'link_expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting) {
            if (! $meeting->public_id) {
                $meeting->public_id = Str::random(21);
            }
        });
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(MeetingInvitation::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(MeetingChatMessage::class);
    }

    /** The room name used on LiveKit. */
    public function roomName(): string
    {
        return $this->public_id;
    }

    /** Shareable guest/anyone link. */
    public function publicLink(): string
    {
        return url('/meet/' . $this->public_id);
    }

    public function isHost(User $user): bool
    {
        return $this->host_user_id === $user->id;
    }

    public function isExpired(): bool
    {
        return $this->link_expires_at !== null && $this->link_expires_at->isPast();
    }

    public function isEnded(): bool
    {
        return $this->status === self::STATUS_ENDED;
    }

    public function effectiveStatus(): string
    {
        if ($this->isEnded()) {
            return self::STATUS_ENDED;
        }
        if ($this->isExpired()) {
            return self::STATUS_EXPIRED;
        }

        return $this->status;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDelivery extends Model
{
    protected $fillable = [
        'user_id', 'to_email', 'subject', 'type', 'linked_type', 'linked_id',
        'status', 'error', 'retries', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'retries' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

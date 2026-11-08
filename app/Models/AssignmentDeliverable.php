<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentDeliverable extends Model
{
    protected $fillable = [
        'assignment_recipient_id', 'original_filename', 'path',
        'mime_type', 'size', 'note', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(AssignmentRecipient::class, 'assignment_recipient_id');
    }
}

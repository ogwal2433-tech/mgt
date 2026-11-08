<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssignmentPart extends Model
{
    protected $fillable = ['assignment_id', 'title', 'due_at', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'due_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(AssignmentDeliverable::class);
    }
}

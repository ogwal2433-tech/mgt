<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskUpdate extends Model
{
    protected $fillable = ['task_id', 'user_id', 'comment', 'progress_percentage', 'hours_spent'];

    protected function casts(): array
    {
        return ['progress_percentage' => 'integer', 'hours_spent' => 'decimal:2'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

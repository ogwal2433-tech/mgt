<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $appends = ['download_url', 'preview_url'];

    protected $fillable = [
        'uuid', 'name', 'original_filename', 'path', 'disk', 'mime_type', 'size',
        'category', 'linked_type', 'linked_id', 'owner_user_id', 'is_confidential',
        'checksum', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_confidential' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * Documents are addressed by their public uuid, not the sequential id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Tokenized download link (signed + expiring).
     */
    public function getDownloadUrlAttribute(): string
    {
        return \App\Support\Links::document($this);
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('documents.preview', $this);
    }
}

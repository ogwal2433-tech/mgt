<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalRequisitionAttachment extends Model
{
    protected $fillable = ['external_requisition_id', 'document_id', 'description'];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(ExternalRequisition::class, 'external_requisition_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractReminder extends Model
{
    protected $fillable = ['contract_id', 'remind_on', 'days_before', 'status', 'sent_at'];

    protected function casts(): array
    {
        return ['remind_on' => 'date', 'sent_at' => 'datetime', 'days_before' => 'integer'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EmployeeContract::class, 'contract_id');
    }
}

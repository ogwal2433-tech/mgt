<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayPeriod extends Model
{
    protected $fillable = ['name', 'year', 'month', 'start_date', 'end_date', 'pay_date', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'pay_date' => 'date', 'year' => 'integer', 'month' => 'integer'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function run(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'computed', 'approved']);
    }
}

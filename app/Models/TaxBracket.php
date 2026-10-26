<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxBracket extends Model
{
    protected $fillable = ['name', 'monthly_lower', 'monthly_upper', 'rate_percent', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'monthly_lower' => 'decimal:2',
            'monthly_upper' => 'decimal:2',
            'rate_percent' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}

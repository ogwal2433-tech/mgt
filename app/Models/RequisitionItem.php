<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    protected $fillable = ['requisition_id', 'description', 'line_type', 'quantity', 'hours', 'unit', 'period', 'unit_cost', 'total_cost', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'sort_order' => 'integer',
            'hours' => 'decimal:2',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public static function computeTotal(string|float $quantity, string|float $unitCost): string
    {
        return Money::mulQty($unitCost, $quantity);
    }

    /**
     * Line total for any line type:
     *   goods    = quantity x unit cost
     *   service  = hours x rate (falls back to quantity x rate)
     *   activity = flat amount (quantity 1)
     */
    public static function computeLineTotal(array $item): string
    {
        $rate = Money::normalize($item['unit_cost'] ?? 0);
        $type = $item['line_type'] ?? 'goods';

        if ($type === 'service' && ! empty($item['hours']) && Money::compare($item['hours'], '0.00') > 0) {
            return Money::mulQty($rate, $item['hours']);
        }

        $quantity = $item['quantity'] ?? 1;

        return Money::mulQty($rate, $quantity ?: 1);
    }
}

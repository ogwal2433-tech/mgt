<?php

namespace App\Support;

use App\Models\SequenceCounter;
use Illuminate\Support\Facades\DB;

/**
 * Transactional document numbering (REQ-2026-00001 etc.).
 */
final class Sequence
{
    public static function next(string $name, string $prefix, int $padding = 5): string
    {
        $year = now()->format('Y');

        return DB::transaction(function () use ($name, $prefix, $padding, $year) {
            $counter = SequenceCounter::lockForUpdate()->firstOrCreate(
                ['name' => $name],
                ['value' => 0]
            );

            $counter->increment('value');
            $counter->refresh();

            return sprintf('%s-%s-%s', $prefix, $year, str_pad((string) $counter->value, $padding, '0', STR_PAD_LEFT));
        });
    }
}

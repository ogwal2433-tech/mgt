<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Fixed-precision monetary arithmetic (bcmath, scale 2).
 * Never use floating point for financial amounts.
 */
final class Money
{
    public const SCALE = 2;

    public static function normalize(int|float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        $raw = is_int($amount) ? (string) $amount : sprintf('%.10F', (float) $amount);

        if (!preg_match('/^-?\d+(\.\d+)?$/', $raw)) {
            throw new InvalidArgumentException("Invalid monetary value: {$amount}");
        }

        return bcadd($raw, '0', self::SCALE);
    }

    public static function add(int|float|string $a, int|float|string $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(int|float|string $a, int|float|string $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function mul(int|float|string $a, int|float|string $b): string
    {
        return bcmul(self::normalize($a), self::normalize($b), self::SCALE);
    }

    /**
     * Multiply a unit cost by a quantity that may carry decimals (e.g. 2.5 hours).
     */
    public static function mulQty(int|float|string $unit, int|float|string $qty): string
    {
        $unitRaw = self::normalize($unit);
        $qtyRaw = is_int($qty) ? (string) $qty : sprintf('%.6F', (float) $qty);

        return bcmul($unitRaw, $qtyRaw, self::SCALE);
    }

    public static function compare(int|float|string $a, int|float|string $b): int
    {
        return bccomp(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function gt(int|float|string $a, int|float|string $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    public static function gte(int|float|string $a, int|float|string $b): bool
    {
        return self::compare($a, $b) >= 0;
    }

    public static function equals(int|float|string $a, int|float|string $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    public static function max(int|float|string $a, int|float|string $b): string
    {
        return self::compare($a, $b) >= 0 ? self::normalize($a) : self::normalize($b);
    }

    public static function min(int|float|string $a, int|float|string $b): string
    {
        return self::compare($a, $b) <= 0 ? self::normalize($a) : self::normalize($b);
    }

    /** @param iterable<int|float|string> $values */
    public static function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    public static function format(int|float|string|null $amount, string $currency = 'USD'): string
    {
        $normalized = self::normalize($amount);
        [$whole, $fraction] = array_pad(explode('.', $normalized), 2, '00');
        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-');
        $whole = number_format((float) $whole, 0, '.', ',');

        return sprintf('%s%s %s%s.%s', $negative ? '-' : '', $currency, '', $whole, $fraction);
    }
}

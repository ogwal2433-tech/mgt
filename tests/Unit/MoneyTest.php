<?php

use App\Support\Money;

it('normalises monetary values to two decimals', function () {
    expect(Money::normalize(10))->toBe('10.00');
    expect(Money::normalize('10.5'))->toBe('10.50');
    expect(Money::normalize(null))->toBe('0.00');
});

it('adds and subtracts with fixed precision', function () {
    expect(Money::add('0.1', '0.2'))->toBe('0.30');
    expect(Money::sub('10.00', '9.99'))->toBe('0.01');
    expect(Money::sum(['1.11', '2.22', '3.33']))->toBe('6.66');
});

it('multiplies quantities without floating point drift', function () {
    expect(Money::mulQty('19.99', 3))->toBe('59.97');
    expect(Money::mulQty('0.07', 100))->toBe('7.00');
});

it('compares values correctly', function () {
    expect(Money::compare('10.00', '10'))->toBe(0);
    expect(Money::gt('10.01', '10.00'))->toBeTrue();
    expect(Money::gte('10.00', '10.00'))->toBeTrue();
    expect(Money::equals('10.00', '10'))->toBeTrue();
});

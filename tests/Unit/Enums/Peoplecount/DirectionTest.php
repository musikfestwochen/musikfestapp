<?php

use App\Enums\Peoplecount\Direction;

it('has correct enum cases', function () {
    // Test that the enum cases exist
    expect(Direction::IN)->toBe(Direction::IN);
    expect(Direction::OUT)->toBe(Direction::OUT);

    // Test that the enum cases have the correct values
    expect(Direction::IN->value)->toBe('in');
    expect(Direction::OUT->value)->toBe('out');
});

it('returns correct labels for enum cases', function () {
    // Test that the label() method returns the correct labels
    expect(Direction::IN->label())->toBe('In');
    expect(Direction::OUT->label())->toBe('Out');
});

it('returns all enum values as array', function () {
    // Test that the values() method returns all enum values
    $values = Direction::values();

    expect($values)->toBe(['in', 'out']);
    expect($values)->toHaveCount(2);
    expect($values)->toContain('in');
    expect($values)->toContain('out');
});

it('can be created from value', function () {
    // Test that an enum can be created from a value
    $direction = Direction::from('in');

    expect($direction)->toBe(Direction::IN);
    expect($direction)->not->toBe(Direction::OUT);
});

it('throws exception for invalid value', function () {
    // Test that an exception is thrown for an invalid value
    // Using a variable to avoid static analysis issues
    $invalidValue = 'invalid';
    expect(fn () => Direction::from($invalidValue))->toThrow(\ValueError::class);
});

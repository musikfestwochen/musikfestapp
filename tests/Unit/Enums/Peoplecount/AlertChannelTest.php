<?php

use App\Enums\Peoplecount\AlertChannel;

describe('AlertChannel enum', function () {
    it('contains expected cases and backing values', function () {
        $cases = AlertChannel::cases();
        expect($cases)->toHaveCount(2);

        $values = array_map(fn ($c) => $c->value, $cases);
        expect($values)->toContain('vonage', 'email');

        expect(AlertChannel::Vonage->value)->toBe('vonage')
            ->and(AlertChannel::Email->value)->toBe('email');
    });
});

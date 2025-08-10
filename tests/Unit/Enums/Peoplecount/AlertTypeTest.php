<?php

use App\Enums\Peoplecount\AlertType;

describe('AlertType enum', function () {
    it('contains expected cases and backing values', function () {
        $cases = AlertType::cases();
        expect($cases)->toHaveCount(1);

        $case = $cases[0];
        expect($case)->toBe(AlertType::OccupancyAlert)
            ->and($case->value)->toBe('occupancy_alert');
    });
});

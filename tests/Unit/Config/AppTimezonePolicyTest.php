<?php

it('enforces UTC application timezone policy', function () {
    expect(config('app.timezone'))->toBe('UTC');
});

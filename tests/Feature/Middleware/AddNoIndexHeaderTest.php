<?php

it('adds the robots header to successful responses', function () {
    $this->get('/up')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
});

it('adds the robots header to missing responses', function () {
    $this->get('/missing')
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet');
});

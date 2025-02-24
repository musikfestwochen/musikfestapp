<?php

use App\Models\User;

test('test fillable attributes', function () {
    $user = User::factory()->create();

    expect($user->getFillable())->toEqualCanonicalizing(['email', 'name', 'password']);

});

it('test hidden attributes', function () {
    $user = User::factory()->create();

    expect($user->getHidden())->toEqualCanonicalizing(['password', 'remember_token']);
});

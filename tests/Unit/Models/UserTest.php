<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notification;

covers(User::class);

it('has correct fillable attributes', function () {
    $user = new User;
    expect($user->getFillable())->toEqualCanonicalizing([
        'name',
        'email',
        'password',
        'phone',
    ]);
});

it('has correct hidden attributes', function () {
    $user = new User;
    expect($user->getHidden())->toEqualCanonicalizing(['password', 'remember_token']);
});

it('implements MustVerifyEmail interface', function () {
    $user = new User;
    expect($user)->toBeInstanceOf(MustVerifyEmail::class);
});

it('organization relationship returns BelongsToMany', function () {
    $reflection = new ReflectionMethod(User::class, 'organizations');
    $returnType = $reflection->getReturnType();

    expect($returnType)
        ->not()->toBeNull()
        ->and($returnType->getName())->toBe(BelongsToMany::class);
});

it('BelongsToMany org', function () {
    $user = new User;
    $relation = $user->organizations();

    expect($relation)->toBeInstanceOf(BelongsToMany::class);
});

it('phone mutator removes spaces and formatting', function () {
    $user = new User;
    $user->phone = '+41 79 123 45 67';

    expect($user->phone)->toBe('+41791234567');

    $user->phone = '+41-79-123-45-67';
    expect($user->phone)->toBe('+41791234567');

    $user->phone = '+41 (79) 123.45.67';
    expect($user->phone)->toBe('+41791234567');

    $user->phone = null;
    expect($user->phone)->toBeNull();
});

it('phone mutator handles all cases', function (?string $input, $expected) {
    $user = new User;
    $user->phone = $input;

    expect($user->phone)->toBe($expected);
})->with([
    ['+41 79 123 45 67', '+41791234567'],
    ['+41-79-123-45-67', '+41791234567'],
    ['+41 (79) 123.45.67', '+41791234567'],
    [null, null],
    ['', null],
    ['0', null],
    ['   ', null],
    ['+41.79.123.45.67', '+41791234567'],
    ['+41(79)1234567', '+41791234567'],
    ['+41-79-123-45-67 ext. 123', '+41791234567ext123'], // extension handling
    ['+41-79-123-45-67#123', '+41791234567#123'], // hash extension
    ['+41-79-123-45-67abc', '+41791234567abc'], // letters
    ['+41-79-123-45-67!', '+41791234567!'], // symbol
]);

it('routeNotificationForVonage returns cleaned phone number', function () {
    $user = new User;
    $user->phone = '+41 79 123 45 67';

    $notification = Mockery::mock(Notification::class);
    expect($user->routeNotificationForVonage($notification))->toBe('+41791234567');
});

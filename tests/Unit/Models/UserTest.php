<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

covers(User::class);

it('has correct fillable attributes', function () {
    $user = new User;
    expect($user->getFillable())->toEqualCanonicalizing([
        'name',
        'email',
        'password',
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

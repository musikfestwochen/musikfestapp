<?php

use App\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

covers(Organization::class);

it('has correct fillable attributes', function () {
    $org = new Organization;
    expect($org->getFillable())->toEqualCanonicalizing([
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'website',
        'logo',
    ]);
});

it('uses SoftDeletes', function () {
    $org = new Organization;
    $traits = class_uses_recursive($org);
    expect($traits)->toContain(SoftDeletes::class);
});

it('users relationship returns BelongsToMany', function () {
    $reflection = new ReflectionMethod(Organization::class, 'users');
    $returnType = $reflection->getReturnType();

    expect($returnType)
        ->not()->toBeNull()
        ->and($returnType->getName())->toBe(BelongsToMany::class);
});

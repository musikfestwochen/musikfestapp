<?php

use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

it('uses the right validation rules', function () {
    $controller = new PasswordController();

    $request = Request::create('/update-password', 'POST', [
        'current_password' => 'current_password_value',
        'password' => 'new_password',
        'password_confirmation' => 'new_password',
    ]);

    try {
        $controller->update($request);
    } catch (ValidationException $e) {
        $rules = $e->validator->getRules();

        expect($rules)->toHaveKey('current_password')
            ->and($rules['current_password'])->toContain('required', 'current_password')
            ->and($rules)->toHaveKey('password')
            ->and($rules['password'])->toContain('required', 'confirmed')
            ->and($rules['password'][1])->toBeInstanceOf(\Illuminate\Validation\Rules\Password::class);

    }
});

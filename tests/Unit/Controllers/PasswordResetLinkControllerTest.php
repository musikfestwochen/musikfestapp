<?php

use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

it('uses the session status in the inertia response', closure: function () {

    session(['status' => 'testStatus']);

    $this->mock(PasswordResetLinkController::class)
        ->shouldReceive('create')
        ->andReturn(Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]));

    $response = (new PasswordResetLinkController())->create();

    expect($response->resolveProperties(request(), ['status']))->toMatchArray([
        'status' => 'testStatus',
    ]);
});

it('validates the email field', function () {

    Password::shouldReceive('sendResetLink')
        ->once()->andReturn(Password::RESET_LINK_SENT);

    $controller = (new PasswordResetLinkController());

    $request = Request::create('/forgot-password', 'POST', [
        'email' => 'test',
    ]);
    expect(fn() => $controller->store($request))->toThrow(ValidationException::class);

    $request = Request::create('/forgot-password', 'POST', [
        'email' => '',
    ]);
    expect(fn() => $controller->store($request))->toThrow(ValidationException::class);

    $request = Request::create('/forgot-password', 'POST', [
        'email' => 'test@gmail.com',
    ]);
    expect($controller->store($request))->toBeInstanceOf(RedirectResponse::class);
});



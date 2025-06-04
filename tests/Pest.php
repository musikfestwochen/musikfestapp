<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
|
| Here you can define helper functions that will be available to your tests.
| These functions can be used to mock dependencies or perform common setup tasks.
|
*/

/**
 * Mock the auth() helper function to return an auth manager that can be configured
 * to return true or false for check() calls.
 *
 * @param  bool  $authenticated  Whether the user is authenticated
 */
function mockAuth(bool $authenticated): void
{
    // Create a mock auth manager that will return the specified authentication status
    $auth = Mockery::mock(Illuminate\Auth\AuthManager::class);
    $auth->shouldReceive('check')->andReturn($authenticated);

    // Bind the auth manager to the container
    app()->instance('auth', $auth);
}

/**
 * Clean up Mockery after each test.
 */
uses()->afterEach(function () {
    Mockery::close();
})->in('Unit/Requests/Admin');

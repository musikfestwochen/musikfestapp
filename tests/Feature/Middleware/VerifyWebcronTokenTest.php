<?php

use App\Http\Middleware\VerifyWebcronToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up test configuration
    Config::set('webcron.secret', 'test-secret-token');

    // Clear cache before each test
    Cache::forget('webcron_allowed_ips');
});

it('allows requests with valid token and allowed IP', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['127.0.0.1', '192.168.1.1', '10.0.0.1'],
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with valid token and allowed IP
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'test-secret-token',
    ]);

    $response = $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('Success');
});

it('blocks requests with invalid token', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['127.0.0.1'],
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with invalid token but allowed IP
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'invalid-token',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('Invalid webcron token provided. Please check your configuration.');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

it('blocks requests with missing token', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['127.0.0.1'],
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request without token but with allowed IP
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('Invalid webcron token provided. Please check your configuration.');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

it('blocks requests from disallowed IP addresses', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['192.168.1.1', '10.0.0.1'],
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with valid token but disallowed IP
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1', // Not in allowed list
        'HTTP_X_WEBCRON_TOKEN' => 'test-secret-token',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('IP address not allowed');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

it('blocks all requests when API is unavailable', function () {
    // Mock API failure
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response('Server Error', 500),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with valid token
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'test-secret-token',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('IP address not allowed');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

it('blocks all requests when API returns invalid data', function () {
    // Mock API with invalid response
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'invalid' => 'data',
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with valid token
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'test-secret-token',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('IP address not allowed');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

it('uses cached IP addresses on subsequent requests', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['127.0.0.1'],
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with valid token and allowed IP
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'test-secret-token',
    ]);

    // First request - should fetch from API
    $response1 = $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });

    // Verify the API was called once
    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://api.cron-job.org/executor-nodes.json';
    });

    // Clear HTTP fake to ensure no more calls are made
    Http::fake([]);

    // Second request - should use cache
    $response2 = $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });

    expect($response1->getStatusCode())->toBe(200);
    expect($response2->getStatusCode())->toBe(200);

    // Verify no additional HTTP calls were made
    Http::assertNothingSent();
});

it('handles network timeouts gracefully', function () {
    // Mock network timeout
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => function (): never {
            throw new ConnectionException('Connection timeout');
        },
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with valid token
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'test-secret-token',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('IP address not allowed');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

it('works with the actual webcron route', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['127.0.0.1'],
        ], 200),
    ]);

    // Test the actual route with valid token and IP
    $response = $this->post('/api/webcron', [], [
        'X-Webcron-Token' => 'test-secret-token',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['schedule_output', 'queue_output']);
});

it('blocks the actual webcron route with invalid credentials', function () {
    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['192.168.1.1'],
        ], 200),
    ]);

    // Test with invalid token
    $response = $this->post('/api/webcron', [], [
        'X-Webcron-Token' => 'invalid-token',
        'REMOTE_ADDR' => '127.0.0.1',
    ]);

    $response->assertStatus(401);

    // Test with valid token but invalid IP
    $response = $this->post('/api/webcron', [], [
        'X-Webcron-Token' => 'test-secret-token',
        'REMOTE_ADDR' => '127.0.0.1', // Not in allowed list
    ]);

    $response->assertStatus(403);
});

it('handles short tokens correctly in maskToken method', function () {
    // Set up a short token (8 characters or less)
    Config::set('webcron.secret', 'short');

    // Mock the HTTP response for allowed IPs
    Http::fake([
        'https://api.cron-job.org/executor-nodes.json' => Http::response([
            'ipAddresses' => ['127.0.0.1'],
        ], 200),
    ]);

    $middleware = new VerifyWebcronToken;

    // Create request with invalid short token
    $request = Request::create('/api/webcron', 'POST', [], [], [], [
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_X_WEBCRON_TOKEN' => 'bad',
    ]);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    $this->expectExceptionMessage('Invalid webcron token provided. Please check your configuration.');

    $middleware->handle($request, function ($req): Response {
        return new Response('Success', 200);
    });
});

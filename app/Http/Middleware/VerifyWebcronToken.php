<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebcronToken
{
    private const string ALLOWED_IPS_CACHE_KEY = 'webcron_allowed_ips';

    private const int CACHE_TTL_MINUTES = 60; // Cache for 1 hour

    private const string EXECUTOR_NODES_URL = 'https://api.cron-job.org/executor-nodes.json';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verify token
        abort_if($request->header('X-Webcron-Token') !== config('webcron.secret'), 401, 'Invalid token');

        // Verify IP address
        abort_unless($this->isAllowedIp($request->ip()), 403, 'IP address not allowed');

        return $next($request);
    }

    /**
     * Check if the given IP address is in the allowed list
     */
    protected function isAllowedIp(string $ip): bool
    {
        $allowedIps = $this->getAllowedIps();

        return in_array($ip, $allowedIps, true);
    }

    /**
     * Get the list of allowed IP addresses (cached)
     *
     * @return array<string>
     */
    protected function getAllowedIps(): array
    {
        return Cache::remember(
            self::ALLOWED_IPS_CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function (): array {
                return $this->fetchAllowedIpsFromApi();
            }
        );
    }

    /**
     * Fetch allowed IP addresses from the cron-job.org API
     *
     * @return array<string>
     */
    protected function fetchAllowedIpsFromApi(): array
    {
        try {
            $response = Http::timeout(10)->get(self::EXECUTOR_NODES_URL);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['ipAddresses']) && is_array($data['ipAddresses'])) {
                    Log::info('Successfully fetched webcron allowed IPs', [
                        'count' => count($data['ipAddresses']),
                    ]);

                    return $data['ipAddresses'];
                }
            }

            Log::warning('Failed to fetch webcron allowed IPs from API', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $exception) {
            Log::error('Exception while fetching webcron allowed IPs', [
                'error' => $exception->getMessage(),
            ]);
        }

        // Return empty array if API fails - this will block all requests
        // This is a security-first approach
        return [];
    }
}

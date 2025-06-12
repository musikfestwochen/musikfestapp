<?php

namespace App\Http\Middleware\Permissions;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationSlugMiddleware
{
    /**
     * Handle an incoming request.
     * Sets the permissions organization ID based on the organization route parameter.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if the route has an organization parameter
        if ($organization = $request->route('organization')) {
            // Set the organization context for permissions
            setPermissionsOrgId($organization->id);
        }

        return $next($request);
    }
}

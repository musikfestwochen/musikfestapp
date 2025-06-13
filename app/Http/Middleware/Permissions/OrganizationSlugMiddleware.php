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
        $organization = $request->route('organization');

        // Set the organization context for permissions
        if ($organization && is_object($organization) && isset($organization->id)) {
            setPermissionsOrgId($organization->id);
        } else {
            // If no organization is found, set the global organization ID
            setPermissionsOrgId(GLOBAL_ORG_ID);
        }

        return $next($request);
    }
}

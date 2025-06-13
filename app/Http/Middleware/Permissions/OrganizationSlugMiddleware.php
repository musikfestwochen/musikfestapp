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
        $user = $request->user();

        // Set the organization context for permissions
        if ($organization && is_object($organization) && isset($organization->id)) {
            if (getPermissionsOrgId() !== $organization->id) {
                // If the current permissions organization ID is different, update it
                setPermissionsOrgId($organization->id);

                if ($user) {
                    $user->unsetRelation('roles')->unsetRelation('permissions');
                }
            }
        } else {
            // If no organization is found, set the global organization ID
            setPermissionsOrgId(GLOBAL_ORG_ID);
        }

        return $next($request);
    }
}

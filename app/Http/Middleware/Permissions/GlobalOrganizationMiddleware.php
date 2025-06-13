<?php

namespace App\Http\Middleware\Permissions;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GlobalOrganizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $user = $request->user();

        if (getPermissionsOrgId() !== GLOBAL_ORG_ID) {
            setPermissionsOrgId(GLOBAL_ORG_ID);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        return $next($request);
    }
}

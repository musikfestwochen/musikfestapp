<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

// Define the global organization ID constant
if (! defined('GLOBAL_ORG_ID')) {
    define('GLOBAL_ORG_ID', 0);
}

if (! function_exists('setPermissionsOrgId')) {
    function setPermissionsOrgId(Model|int|string|null $id): void
    {
        resolve(PermissionRegistrar::class)->setPermissionsTeamId($id);
    }
}

if (! function_exists('getPermissionsOrgId')) {
    function getPermissionsOrgId(): int|string|null
    {
        return resolve(PermissionRegistrar::class)->getPermissionsTeamId();
    }
}

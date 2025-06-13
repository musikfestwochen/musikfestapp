<?php

namespace App\Listeners\Permissions;

use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Permission\Events\PermissionAttached;

class PermissionAttachedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected GlobalPermissionService $globalPermissionService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PermissionAttached $event): void
    {
        // The event can receive the permission details as a model ID, Eloquent record, array, or collection
        // We need to extract the user ID from the event
        $userId = $this->getUserId($event);

        if ($userId) {
            // Clear existing cache for this user to ensure fresh data
            $this->globalPermissionService::clearCache($userId);

            // The cache will be rebuilt on the next permission check
            // This is handled by the GlobalPermissionService::canGlobally method
        }
    }

    /**
     * Extract the user ID from the event
     */
    public function getUserId(PermissionAttached $event): ?int
    {
        if (isset($event->model) && $event->model instanceof User) {
            return $event->model->id;
        }

        if (property_exists($event, 'modelId') && $event->modelId !== null) {
            return $event->modelId;
        }

        return null;
    }
}

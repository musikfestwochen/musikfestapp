<?php

namespace App\Listeners\Permissions;

use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Permission\Events\RoleDetached;

class RoleDetachedListener implements ShouldQueue
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
    public function handle(RoleDetached $event): void
    {
        // The event can receive the role details as a model ID, Eloquent record, array, or collection
        // We need to extract the user ID from the event
        $userId = $this->getUserId($event);

        if ($userId) {
            // Delete all global cached permissions for this user
            $this->globalPermissionService::clearCache($userId);
        }
    }

    /**
     * Extract the user ID from the event
     */
    public function getUserId(RoleDetached $event): ?int
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

<?php

declare(strict_types=1);

namespace App\Listeners\Permissions;

use App\Models\User;
use App\Services\GlobalPermissionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Permission\Events\RoleDetachedEvent;

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
    public function handle(RoleDetachedEvent $event): void
    {
        // The event can receive the role details as a model ID, Eloquent record, array, or collection
        // We need to extract the user ID from the event
        $userId = $this->getUserId($event);

        if ($userId !== null && $userId !== 0) {
            // Delete all global cached permissions for this user
            GlobalPermissionService::clearCache($userId);
        }
    }

    /**
     * Extract the user ID from the event
     */
    public function getUserId(RoleDetachedEvent $event): ?int
    {
        if (isset($event->model) && $event->model instanceof User) {
            return $event->model->id;
        }

        return null;
    }
}

<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Support\BusinessAccess;

class ServicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return BusinessAccess::canAccess($user, $business->id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Service $service): bool
    {
        return BusinessAccess::canAccess(
            $user,
            $service->business_id
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Business $business): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess($user, $business->id);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Service $service): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $service->business_id
            );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Service $service): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $service->business_id
            );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Service $service): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return false;
    }
}

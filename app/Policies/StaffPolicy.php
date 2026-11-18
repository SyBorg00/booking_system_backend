<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Staff;
use App\Models\User;
use App\Support\BusinessAccess;

class StaffPolicy
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
    public function view(User $user, Staff $staff): bool
    {
        // Super admins and assigned admins can view staff details.
        if (in_array($user->role, ['super_admin', 'admin'], true)) {
            return BusinessAccess::canAccess(
                $user,
                $staff->business_id
            );
        }

        // Staff members can only view their own staff record.
        if ($user->role === 'staff') {
            return $user->staff()
                ->whereKey($staff->id)
                ->exists();
        }

        return false;
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
    public function update(User $user, Staff $staff): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $staff->business_id
            );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Staff $staff): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $staff->business_id
            );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Staff $staff): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Staff $staff): bool
    {
        return false;
    }
}

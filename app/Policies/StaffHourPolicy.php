<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\StaffHour;
use App\Models\User;
use App\Support\BusinessAccess;

class StaffHourPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Staff $staff): bool
    {
        if ($user->role === 'staff') {
            return $user->staff()
                ->whereKey($staff->id)
                ->exists();
        }

        return BusinessAccess::canAccess($user, $staff->business_id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StaffHour $staffHour): bool
    {
        if ($user->role === 'staff') {
            return $user->staff()
                ->whereKey($staffHour->staff_id)
                ->exists();
        }

        return BusinessAccess::canAccess(
            $user,
            $staffHour->staff->business_id
        );
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Staff $staff): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess($user, $staff->business_id);
    }

    /**
     * Determine whether the user can update the model.
     */
     public function update(User $user, StaffHour $staffHour): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $staffHour->staff->business_id
            );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StaffHour $staffHour): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $staffHour->staff->business_id
            );
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StaffHour $staffHour): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StaffHour $staffHour): bool
    {
        return false;
    }
}

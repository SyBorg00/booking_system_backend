<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BusinessPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */

    public function view(User $user, Business $business): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super admins can access every business.
        |--------------------------------------------------------------------------
        */
        if ($user->role === 'super_admin') {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Admins can access businesses they are assigned to.
        |--------------------------------------------------------------------------
        */
        if ($user->role === 'admin') {
            return $user->businesses()
                ->where('businesses.id', $business->id)
                ->exists();
        }

        /*
        |--------------------------------------------------------------------------
        | Staff can access their own business.
        |--------------------------------------------------------------------------
        */
        if ($user->role === 'staff') {
            return $user->staff
                && $user->staff->business_id === $business->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Business $business): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Business $business): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Business $business): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Business $business): bool
    {
        return false;
    }
}

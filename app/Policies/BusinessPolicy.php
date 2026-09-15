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
        /*
        |--------------------------------------------------------------------------
        | Any users with the role of super_admin, admin, or staff can view businesses.
        |--------------------------------------------------------------------------
        */
        return in_array($user->role, [
            'super_admin',
            'admin',
            'staff',
        ]);
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
        /*
        |--------------------------------------------------------------------------
        | Only super admins can create businesses.
        |--------------------------------------------------------------------------
        */
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Business $business): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super admins can update every business. Admins can update businesses they are assigned to.
        |--------------------------------------------------------------------------
        */
        if ($user->role === 'super_admin') {
            return true;
        }

        return $user->role === 'admin'
            && $business->staff()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Business $business): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Only super admins can delete businesses.
        |--------------------------------------------------------------------------
        */
        return $user->role === 'super_admin';
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

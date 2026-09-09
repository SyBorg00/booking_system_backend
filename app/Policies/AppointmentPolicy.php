<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\Response;


// AppointmentPolicy class defines the authorization logic for the Appointment model.
class AppointmentPolicy
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
    public function view(User $user, Appointment $appointment): bool
    {
        /* 
        |-------------------------------------------------------------------------- 
        | Super admins can access appointments across all businesses. 
        |-------------------------------------------------------------------------- 
        */
        if ($user->role === 'super_admin') {
            return true;
        }

        /* 
        |-------------------------------------------------------------------------- 
        | Admins can access appointments for the business they belong to:
        |-------------------------------------------------------------------------- 
        |
        | This checks if the user has an 'admin' role and if they are
        | associated with the business that owns the appointment.
        | If returned false, access is denied (uses BusinessUsers pivot table)
        */
        if ($user->role === 'admin') {
            return $user->businesses()
                ->where('business_id', $appointment->business_id)
                ->exists();
        }

        /* 
        |-------------------------------------------------------------------------- 
        | Staff members can access appointments belonging to their business only
        |--------------------------------------------------------------------------
        |
        | This checks if the user has a 'staff' role and if they are 
        | associated with the business that owns the appointment. Otherwise 
        | access is denied 
        */
        if ($user->role === 'staff') {
            return $user->staff
                && $user->staff->business_id === $appointment->business_id;
        }

        /* 
        |-------------------------------------------------------------------------- 
        | Anything else returns false
        |-------------------------------------------------------------------------- 
        */
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
    public function update(User $user, Appointment $appointment): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return $user->businesses()
                ->where('businesses.id', $appointment->business_id)
                ->exists();
        }

        if ($user->role === 'staff') {
            return $user->staff
                && $user->staff->business_id === $appointment->business_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}

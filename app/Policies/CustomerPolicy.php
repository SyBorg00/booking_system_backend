<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use App\Support\BusinessAccess;

class CustomerPolicy
{   
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Business $business): bool
    {
        return BusinessAccess::canAccess($user, $business->id);
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return BusinessAccess::canAccess(
            $user,
            $customer->business_id
        );
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user, Business $business): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess($user, $business->id);
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $customer->business_id
            );
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess(
                $user,
                $customer->business_id
            );
    }

    /**
     * Determine whether the user can restore the customer.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the customer.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
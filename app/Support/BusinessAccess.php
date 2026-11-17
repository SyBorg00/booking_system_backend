<?php

namespace App\Support;


// Helper class to check if a user has access to a business based on their role and association with the business. Used to centralize the access logic
// for businesses across the application -- ya know, to not repeat the same logic in multiple policies.
class BusinessAccess
{
    /**
     * Check if the user has access to the business. 
     */
    public static function canAccess($user, $businessId): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'admin') {
            return $user->businesses()->where('business_id', $businessId)->exists();
        }

        if ($user->role === 'staff') {
            return $user->staff()->where('business_id', $businessId)->exists();
        }

        return false;
    }
}
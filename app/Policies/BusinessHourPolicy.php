<?php

namespace App\Policies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\User;
use App\Support\BusinessAccess;

class BusinessHourPolicy
{
    
    public function viewAny(User $user, Business $business): bool
    {
        return BusinessAccess::canAccess($user, $business->id);
    }

    public function updateSchedule(User $user, Business $business): bool
    {
        return in_array($user->role, ['super_admin', 'admin'], true)
            && BusinessAccess::canAccess($user, $business->id);
}
}

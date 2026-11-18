<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffHourRequest;
use App\Http\Requests\UpdateStaffHourRequest;
use App\Models\Business;
use App\Models\Staff;
use App\Models\StaffHour;

/*
|--------------------------------------------------------------------------
| StaffHourController
|--------------------------------------------------------------------------
| For this controller, it needs to ensure that the business id and the staff id 
| must be specified first and foremost. Otherwise, it would cause some unforeseen
| problems in the near future, like for example, another member from a different 
| business manipulating the data of another member from a different business
*/

class StaffHourController extends Controller
{

    //load the staff hours for a specific staff member in a business
    public function index(Business $business, Staff $staff)
    {
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $this->authorize('viewAny', [StaffHour::class, $staff]);

        return response()->json([
            'data' => $staff->hours()
                ->orderBy('day_of_week')
                ->get()
        ]);
    }

    //store a new staff hour for a specific staff member in a business
    public function store(
        StoreStaffHourRequest $request,
        Business $business,
        Staff $staff
    ) {
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $this->authorize('create', [StaffHour::class, $staff]);

        $staffHour = $staff->hours()->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Staff hour created successfully.',
            'data' => $staffHour
        ], 201);
    }

    //show a specific staff hour for a specific staff member in a business
    public function show(
        Business $business,
        Staff $staff,
        StaffHour $staffHour
    ) {
        /*
        |--------------------------------------------------------------------------
        | Ownership checking and authorization: Ensure that the staff member belongs to the business and the staff 
        | hour belongs to the staff member before showing the staff hour
        |--------------------------------------------------------------------------
        */
        $staff = $business->staff()
        ->whereKey($staff->id)
        ->firstOrFail();

        $staffHour = $staff->hours()
            ->whereKey($staffHour->id)
            ->firstOrFail();

        $this->authorize('view', $staffHour);

        return response()->json(
            [
                'data' => $staffHour
            ]
        );
    }

    //update a specific staff hour for a specific staff member in a business
    public function update(
        UpdateStaffHourRequest $request,
        Business $business,
        Staff $staff,
        StaffHour $staffHour
    ) {
        /*
        |--------------------------------------------------------------------------
        | Ownership checking and authorization: Ensure that the staff member belongs to the business and the staff 
        | hour belongs to the staff member before updating the staff hour
        |--------------------------------------------------------------------------
        */
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $staffHour = $staff->hours()
            ->whereKey($staffHour->id)
            ->firstOrFail();

        $this->authorize('update', $staffHour);

        $staffHour->update($request->validated());

        $staffHour->update(
            $request->validated()
        );

        return response()->json(
            [
                'message' => 'Staff hour updated successfully.',
                'data' => $staffHour->fresh()
            ]
        );
    }

    //delete a specific staff hour for a specific staff member in a business
    public function destroy(
        Business $business,
        Staff $staff,
        StaffHour $staffHour
    ) {
        /*
        |--------------------------------------------------------------------------
        | Ownership checking and authorization: Ensure that the staff member belongs to the business and the staff 
        | hour belongs to the staff member before deleting the staff hour
        |--------------------------------------------------------------------------
        */
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $staffHour = $staff->hours()
            ->whereKey($staffHour->id)
            ->firstOrFail();

        $this->authorize('delete', $staffHour);

        $staffHour->delete();

        $staffHour->delete();

        return response()->json([
            'message' => 'Staff hour deleted successfully.',
        ]);
    }
}

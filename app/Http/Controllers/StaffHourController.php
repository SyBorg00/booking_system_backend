<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffHourRequest;
use App\Http\Requests\UpdateStaffHourRequest;
use App\Models\Business;
use App\Models\Staff;
use App\Models\StaffHour;

class StaffHourController extends Controller
{
    /* For this controller, it needs to ensure that the business id and the staff id must be specified first and foremost.
    Otherwise, it would cause some unforeseen problems in the near future, like for example, another member from a different business manipulating 
    the data of another member from a different business*/

    //load the staff hours for a specific staff member in a business
    public function index(
        Business $business,
        Staff $staff
    ) {
        abort_unless(
            $staff->business_id ===  $business->id,
            404
        );

        return response()->json(
            $staff->staffHours()
                ->orderBy('day_of_week')
                ->get()
        );
    }

    //store a new staff hour for a specific staff member in a business
    public function store(
        StoreStaffHourRequest $request,
        Business $business,
        Staff $staff
    ) {
        abort_unless(
            $staff->business_id ===  $business->id,
            404
        );

        $staffHour = $staff->staffHours()->create(
            $request->validated()
        );

        return response()->json(
            $staffHour,
            201
        );
    }

    //show a specific staff hour for a specific staff member in a business
    public function show(
        Business $business,
        Staff $staff,
        StaffHour $staffHour
    ) {
        abort_unless(
            $staff->business_id ===  $business->id,
            404
        );

        abort_unless(
            $staffHour->staff_id ===  $staff->id,
            404
        );

        return response()->json($staffHour);
    }

    //update a specific staff hour for a specific staff member in a business
    public function update(
        UpdateStaffHourRequest $request,
        Business $business,
        Staff $staff,
        StaffHour $staffHour
    ) {
        abort_unless(
            $staff->business_id ===  $business->id,
            404
        );

        abort_unless(
            $staffHour->staff_id ===  $staff->id,
            404
        );

        $staffHour->update(
            $request->validated()
        );

        return response()->json(
            $staffHour->fresh()
        );
    }

    //delete a specific staff hour for a specific staff member in a business
    public function destroy(
        Business $business,
        Staff $staff,
        StaffHour $staffHour
    ) {
        abort_unless(
            $staff->business_id ===  $business->id,
            404
        );

        abort_unless(
            $staffHour->staff_id ===  $staff->id,
            404
        );

        $staffHour->delete();

        return response()->json([
            'message' => 'Staff hour deleted successfully.',
        ]);
    }
}

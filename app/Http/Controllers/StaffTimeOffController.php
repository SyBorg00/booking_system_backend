<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffTimeOffRequest;
use App\Http\Requests\UpdateStaffTimeOffRequest;
use App\Models\Business;
use App\Models\Staff;
use App\Models\StaffTimeOff;

class StaffTimeOffController extends Controller
{
    //fetch the time-off records of a specific staff member
    public function index(
        Business $business,
        Staff $staff
    ) {
        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        return response()->json(
            $staff->timeOffs()
                ->orderBy('start_datetime')
                ->get()
        );
    }

    //create a new time-off record for a specific staff member
    public function store(
        StoreStaffTimeOffRequest $request,
        Business $business,
        Staff $staff
    ) {
        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        $timeOff = $staff->timeOffs()->create(
            $request->validated()
        );

        return response()->json(
            $timeOff,
            201
        );
    }

    //show a specific time-off record for a specific staff member
    public function show(
        Business $business,
        Staff $staff,
        StaffTimeOff $staffTimeOff
    ) {
        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        abort_unless(
            $staffTimeOff->staff_id === $staff->id,
            404
        );

        return response()->json($staffTimeOff);
    }

    //update a specific time-off record for a specific staff member
    public function update(
        UpdateStaffTimeOffRequest $request,
        Business $business,
        Staff $staff,
        StaffTimeOff $staffTimeOff
    ) {
        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        abort_unless(
            $staffTimeOff->staff_id === $staff->id,
            404
        );

        $staffTimeOff->update(
            $request->validated()
        );

        return response()->json(
            $staffTimeOff->fresh()
        );
    }

    //delete a specific time-odd record for a specific staff member
    public function destroy(
        Business $business,
        Staff $staff,
        StaffTimeOff $staffTimeOff
    ) {
        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        abort_unless(
            $staffTimeOff->staff_id === $staff->id,
            404
        );

        $staffTimeOff->delete();

        return response()->json([
            'message' => 'Staff time off deleted successfully.',
        ]);
    }
}

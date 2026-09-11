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
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing the list of a specific staff member's time-off records.
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

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
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a specific staff member's time-off records.
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

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
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing a specific staff member's specific time-off record
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

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
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before updating of a specific staff member's time-off record.
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

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
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before deleting specific staff member's time-off record.
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

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

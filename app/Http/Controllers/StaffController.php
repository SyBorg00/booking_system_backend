<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Business;
use App\Models\Staff;

class StaffController extends Controller
{

    //For the entire StaffController, the 'user' must be used all the time (ensure that the staff member has a user account, otherwise 
    //it doesn't make sense to have a staff member without a user account)


    //fetch the list of staff for a specific business
    public function index(Business $business)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        return response()->json(
            $business->staff()->with('user')->get()
        );
    }

    //create and store staff
    public function store(
        StoreStaffRequest $request,
        Business $business
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        $staff = $business->staff()->create(
            $request->validated()
        );

        return response()->json(
            $staff->load('user'),
            201
        );
    }

    //show staff detail
    public function show(
        Business $business,
        Staff $staff
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        return response()->json(
            $staff->load('user')
        );
    }

    //update staff
    public function update(
        UpdateStaffRequest $request,
        Business $business,
        Staff $staff
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before updating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);
        abort_unless(
            $staff->business_id ===  $business->id,
            404
        );

        $staff->update(
            $request->validated()
        );

        return response()->json(
            $staff->fresh()->load('user')
        );
    }

    //delete staff
    public function destroy(
        Business $business,
        Staff $staff
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before deleting a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);
        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully.',
        ]);
    }
}

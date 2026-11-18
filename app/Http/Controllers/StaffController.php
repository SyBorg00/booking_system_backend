<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Models\Business;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{

    //For the entire StaffController, the 'user' must be used all the time (ensure that the staff member has a user account, otherwise 
    //it doesn't make sense to have a staff member without a user account)


    //fetch the list of staff for a specific business
    public function index(Business $business)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the staff model
        |--------------------------------------------------------------------------
        */
        $this->authorize('viewAny', [Staff::class, $business]);

        return response()->json(
            [
                'data' => $business->staff()
                    ->with('user')
                    ->get()
            ]
        );
    }

    //create and store staff
    public function store(
        StoreStaffRequest $request,
        Business $business
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to create a staff member
        |--------------------------------------------------------------------------
        */
        $this->authorize('create', [Staff::class, $business]);

        $staff = $business->staff()->create(
            $request->validated()
        );

        return response()->json(
            [
                'message' => 'Staff created successfully.',
                'data' => $staff->load('user')
            ],
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
        | Ownership check and Authorization: Ensure that the staff member belongs to the business and that 
        | the user has permission to view the staff member
        |--------------------------------------------------------------------------
        */
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $this->authorize('view', $staff);

        return response()->json(
            [
                'data' => $staff->load('user')
            ]
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
        | Ownership check and Authorization: Ensure that the staff member belongs to the business and that 
        | the user has permission to update the staff member
        |--------------------------------------------------------------------------
        */
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $this->authorize('update', $staff);

        $validated = $request->validated();

        DB::transaction(function () use ($staff, $validated) {
            $staff->user->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]);
    
            $staffData = array_intersect_key(
                $validated,
                array_flip(['phone', 'position'])
            );

            if (!empty($staffData)) {
                $staff->update($staffData);
            }
        });
    
        $staff->load('user');

        return response()->json(
            [
                'message' => 'Staff updated successfully.',
                'data' => $staff->fresh()->load('user')
            ]
        );
    }

    //delete staff
    public function destroy(
        Business $business,
        Staff $staff
    ) {
        /*
        |--------------------------------------------------------------------------
        | Ownership check and Authorization: Ensure that the staff member belongs to the business and that 
        | the user has permission to delete the staff member
        |--------------------------------------------------------------------------
        */
        $staff = $business->staff()
            ->whereKey($staff->id)
            ->firstOrFail();

        $this->authorize('delete', $staff);

        $staff->delete();

        return response()->json([
            'message' => 'Staff deleted successfully.',
        ]);
    }
}

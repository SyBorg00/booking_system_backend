<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStaffServiceRequest;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;

class StaffServiceController extends Controller
{
    //list all services assigned to a staff member
    public function index(
        Business $business,
        Staff $staff
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing a staff-service relationship
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        return response()->json([
            'data' => $staff->services()->get(),
        ]);
    }

    //assign a service to a staff member
    public function store(
        StoreStaffServiceRequest $request,
        Business $business,
        Staff $staff
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a staff-service relationship
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        /*
        |--------------------------------------------------------------------------
        | Fetch the service from the database and ensure it belongs to the same business as the staff member.
        | If the service is already assigned to the staff member, return a 422 response with an appropriate message.
        |--------------------------------------------------------------------------
        */
        $service = Service::where('business_id', $business->id)
            ->findOrFail($request->validated()['service_id']);

        if ($staff->services()->where('services.id', $service->id)->exists()) {
            return response()->json([
                'message' => 'Staff member is already assigned to this service.',
            ], 422);
        }
        /*
        |--------------------------------------------------------------------------
        | Attach the service to the staff member and return a success response.
        |--------------------------------------------------------------------------
        */
        $staff->services()->attach($service->id);

        return response()->json([
            'message' => 'Service assigned to staff successfully.',
            'data' => $service,
        ], 201);
    }

    //remove a service from a staff member
    public function destroy(
        Business $business,
        Staff $staff,
        Service $service
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before deleting a staff-service relationship
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        abort_unless(
            $staff->business_id === $business->id,
            404
        );

        abort_unless(
            $service->business_id === $business->id,
            404
        );
        /*
        |--------------------------------------------------------------------------
        | Detach the service from the staff member and return a success response.
        |--------------------------------------------------------------------------
        */
        $staff->services()->detach($service->id);

        return response()->json([
            'message' => 'Service removed from staff successfully.',
        ]);
    }

    //NOTE: there is no update method because updating a staff-service relationship doesn't make sense in this context.
    //You can only assign or remove services from a staff member.
}

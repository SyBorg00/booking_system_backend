<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function index(
        Request $request,
        Business $business,
        AvailabilityService $availabilityService
    ) {
        //Validate the requested params
        $validated = $request->validate([
            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
            ],

            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
            ],

            'date' => [
                'required',
                'date',
            ],
        ]);

        //Retrieve the requested staff member
        $staff = Staff::findOrFail(
            $validated['staff_id']
        );

        //Retrieve requested service
        $service = Service::findOrFail(
            $validated['service_id']
        );


        // Making sure the staff belongs to the requested business.
        if ($staff->business_id !== $business->id) {
            return response()->json([
                'message' => 'The selected staff member does not belong to this business.',
            ], 404);
        }


        //Making sure the service belongs to the requested business.
        if ($service->business_id !== $business->id) {
            return response()->json([
                'message' => 'The selected service does not belong to this business.',
            ], 404);
        }

        //Call AvailabilityService.js to generate available slots
        $slots = $availabilityService->getAvailableSlots(
            $staff,
            $service,
            $validated['date']
        );

        //Return the availability list
        return response()->json([
            'date' => $validated['date'],

            'staff' => [
                'id' => $staff->id,
                'last_name' => $staff->user->last_name ?? null,
                'first_name' => $staff->user->first_name ?? null,
            ],

            'service' => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'buffer_minutes' => $service->buffer_minutes,
            ],

            'available_slots' => $slots,
        ]);
    }
}

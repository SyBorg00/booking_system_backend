<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AvailabilityController extends Controller
{
    //For legacy purpose, generate a slot w/ only one service
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
        $slots = $availabilityService->getAvailableSlotsForServices(
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

    //this one is specific to generating slots for w/ multiple services
    public function generateSlots(
        Request $request,
        Business $business,
        AvailabilityService $availabilityService
    ) {

        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before accessing this endpoint.
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        *--------------------------------------------------------------------------------------
        | Ensure that the requested staff member and services belong to the requested business.
        *--------------------------------------------------------------------------------------
        */
        $validated = $request->validate([
            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
            ],

            'service_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'service_ids.*' => [
                'integer',
                'exists:services,id',
            ],

            'date' => [
                'required',
                'date',
            ],
        ]);

        /*
        |---------------------------------------------------------------------------------------------
        | Retrieve the requested staff member and services from the database using the validated input.
        |---------------------------------------------------------------------------------------------
        */
        $staff = Staff::findOrFail(
            $validated['staff_id']
        );

        $services = Service::whereIn(
            'id',
            $validated['service_ids']
        )->get();


        /*
        |==========================
        | VALIDATION SUB-SECTION    
        |==========================
        */

        /*
        |---------------------------------------------------------------------------------------------
        | Making sure the requested staff member and services belong to the requested business.
        |---------------------------------------------------------------------------------------------
        */
        if ($staff->business_id !== $business->id) {
            return response()->json([
                'message' => 'The selected staff member does not belong to this business.',
            ], 404);
        }

        $invalidService = $services->first(
            fn($service) => $service->business_id !== $business->id
        );

        if ($invalidService) {
            return response()->json([
                'message' => 'The selected service does not belong to this business.',
            ], 404);
        }

        /*
        |---------------------------------------------------------------------------------------------
        | Call the AvailabilityService to generate available slots for the requested staff member 
        | and services on the specified date.
        |---------------------------------------------------------------------------------------------
        */
        $slots = $availabilityService->getAvailableSlotsForServices(
            $staff,
            $services,
            $validated['date']
        );

        /*
        |---------------------------------------------------------------------------------------------
        | Return the availability list as a JSON response, including the date, staff member details,
        | service details, and available slots.
        |---------------------------------------------------------------------------------------------
        */
        return response()->json([
            'date' => $validated['date'],

            'staff' => [
                'id' => $staff->id,
                'last_name' => $staff->user->last_name ?? null,
                'first_name' => $staff->user->first_name ?? null,
            ],
            'services' => $services->map(
                function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'duration_minutes' => $service->duration_minutes,
                        'buffer_minutes' => $service->buffer_minutes,
                    ];
                }
            )->values(),

            'available_slots' => $slots,
        ]);
    }
}

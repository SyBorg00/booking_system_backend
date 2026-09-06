<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    //Fetch appointments based on filters like business, staff, customer, status, and date
    public function index(Request $request)
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],

            'staff_id' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],

            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],

            'status' => [
                'nullable',
                'in:pending,confirmed,completed,cancelled,no_show',
            ],

            'date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
        ]);

        $query = Appointment::where(
            'business_id',
            $validated['business_id']
        );

        if (!empty($validated['staff_id'])) {
            $query->where(
                'staff_id',
                $validated['staff_id']
            );
        }

        if (!empty($validated['customer_id'])) {
            $query->where(
                'customer_id',
                $validated['customer_id']
            );
        }

        if (!empty($validated['status'])) {
            $query->where(
                'status',
                $validated['status']
            );
        }

        if (!empty($validated['date'])) {
            $query->whereDate(
                'start_datetime',
                $validated['date']
            );
        }

        $appointments = $query
            ->with([
                'customer',
                'staff',
                'appointmentServices.service',
            ])
            ->orderBy('start_datetime')
            ->get();

        return response()->json([
            'appointments' => $appointments,
        ]);
    }

    //Adds a new appointment for a business, customer, and staff member
    public function store(
        Request $request,
        AvailabilityService $availabilityService
    ) {

        /*
        |--------------------------------------------------------------------------
        | Verification request are put in here instead of creating another request file
        |--------------------------------------------------------------------------
        */
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id'
            ],
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id'
            ],
            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id'
            ],
            'start_datetime' => [
                'required',
                'date'
            ],
            'services' => [
                'required',
                'array',
                'min:1'
            ],
            'services.*.service_id' => [
                'required',
                'integer',
                'distinct',
                'exists:services,id',
            ],
            'notes' => [
                'nullable',
                'string'
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Retrieve business
        |--------------------------------------------------------------------------
        */
        $business = Business::findOrFail($validated['business_id']);

        /*
        |--------------------------------------------------------------------------
        | Verify that the customer belongs to the business 
        |--------------------------------------------------------------------------
        */
        $customer = Customer::where('id', $validated['customer_id'])
            ->where('business_id', $business->id)
            ->first();

        if (!$customer) {
            throw ValidationException::withMessages([
                'customer_id' => ['The selected customer does not belong to this business.',],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify that the staff belongs to the business
        |--------------------------------------------------------------------------
        */
        $staff = Staff::where('id', $validated['staff_id'])
            ->where('business_id', $business->id)
            ->first();

        if (!$staff) {
            throw ValidationException::withMessages([
                'staff_id' => ['The selected staff member does not belong to this business.'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Retrieve services and verify that they belong to the business
        |--------------------------------------------------------------------------
        */
        $serviceIds = collect($validated['services'])
            ->pluck('service_id');

        $services = Service::whereIn('id', $serviceIds)
            ->where('business_id', $business->id)
            ->get();

        if ($services->count() !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'services' => ['One or more selected services do not belong to this business.'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate total appointment duration
        |--------------------------------------------------------------------------
        */
        $totalMinutes = $services->sum(function ($service) {
            return $service->duration_minutes
                + $service->buffer_minutes;
        });
        if ($totalMinutes <= 0) {
            throw ValidationException::withMessages(['services' =>
            ['The selected services have an invalid duration.',],]);
        }

        $start = Carbon::parse($validated['start_datetime']);
        $end = $start->copy()->addMinutes($totalMinutes);

        /*
        |--------------------------------------------------------------------------
        | Check staff availability for the requested time slot (NOW USES VALIDATESLOT from AvailabilityService)
        |--------------------------------------------------------------------------
        */

        $conflictMessage = $availabilityService->validateSlot(
            $staff,
            $start,
            $totalMinutes
        );

        if ($conflictMessage !== null) {
            throw ValidationException::withMessages(
                ['start_datetime' => [$conflictMessage,],]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create appointment and service snapshots
        |--------------------------------------------------------------------------
        */
        $appointment = DB::transaction(function () use (
            $business,
            $validated,
            $services,
            $start,
            $end,
        ) {
            $appointment = Appointment::create([
                'business_id' => $validated['business_id'],
                'customer_id' => $validated['customer_id'],
                'staff_id' => $validated['staff_id'],
                'start_datetime' => $start,
                'end_datetime' => $end,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($services as $service) {
                AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'price' => $service->price,
                    'currency' => $business->currency,
                    'duration_minutes' => $service->duration_minutes,
                    'buffer_minutes' => $service->buffer_minutes,
                ]);
            }

            return $appointment;
        });

        return response()->json([
            'message' => 'Appointment created successfully.',
            'appointment' => $appointment->load('appointmentServices.service'),
        ], 201);
    }

    //Fetch a specific appointment by its ID, including related customer, staff, and services
    public function show(Appointment $appointment)
    {
        $appointment->load([
            'customer',
            'staff',
            'appointmentServices.service'
        ]);

        return response()->json([
            'appointment' => $appointment,
        ]);
    }

    //Update a specific appointment's status and notes; ignore everything else, as they are not allowed to be updated after creation (FOR NOW)
    public function update(Request $request, Appointment $appointment)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation variables to be used
        |--------------------------------------------------------------------------
        */
        $validated = $request->validate([
            'status' => [
                'sometimes',
                'in:pending,confirmed,completed,cancelled,no_show',
            ],

            'notes' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validate the status change (if the appointment is already completed or cancelled, it cannot be rescheduled)
        |--------------------------------------------------------------------------
        */
        if (
            isset($validated['status']) && !$appointment->canTransitionTo($validated['status'])
        ) {
            throw ValidationException::withMessages([
                'status' => ["The appointment cannot transition from "
                    . "'{$appointment->status}' to "
                    . "'{$validated['status']}'.",],
            ]);
        }

        $appointment->update($validated);

        return response()->json([
            'message' => 'Appointment updated successfully.',
            'appointment' => $appointment->fresh([
                'customer',
                'staff',
                'appointmentServices.service',
            ]),
        ]);
    }

    //Reschedule an appointment to a new time and/or staff member [Really heavy verification process]
    public function reschedule(
        Request $request,
        Appointment $appointment,
        AvailabilityService $availabilityService
    ) {
        $validated = $request->validate([
            'staff_id' => [
                'sometimes',
                'integer',
                'exists:staff,id',
            ],

            'start_datetime' => [
                'required',
                'date',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Determine the new staff member (if not provided, use the current staff member)
        |--------------------------------------------------------------------------
        */
        $staffId = $validated['staff_id'] ?? $appointment->staff_id;

        $staff = Staff::findOrFail($staffId);

        /*
        |--------------------------------------------------------------------------
        | Verify that the staff belongs to the same business
        |--------------------------------------------------------------------------
        */
        if ($staff->business_id !== $appointment->business_id) {
            return response()->json([
                'message' => 'The selected staff member does not belong to this business.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Get the services attached to the appointment
        |--------------------------------------------------------------------------
        */
        $appointment->load('appointmentServices');

        $appointmentServices = $appointment->appointmentServices;

        if ($appointmentServices->isEmpty()) {
            return response()->json([
                'message' => 'The appointment has no services attached to it.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate total appointment duration
        |--------------------------------------------------------------------------
        |
        | Calcuated using the SNAPSHOT values stored in appointment_services rather
        | than the current service definitions. It may be possible that the service definitions have changed since the appointment was created - Hence,
        | the importance of using the snapshot values.
        |
        */
        $totalDuration = $appointmentServices->sum(
            function ($appointmentService) {
                return $appointmentService->duration_minutes
                    + $appointmentService->buffer_minutes;
            }
        );

        if ($totalDuration <= 0) {
            return response()->json([
                'message' => 'The appointment has an invalid duration.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate new end time
        |--------------------------------------------------------------------------
        */

        $start = Carbon::parse(
            $validated['start_datetime']
        );

        $end = $start->copy()->addMinutes($totalDuration);

        /*
        |--------------------------------------------------------------------------
        | Validate the requested time slots
        |--------------------------------------------------------------------------
        */
        $conflictMessage = $availabilityService->validateSlot(
            $staff,
            $start,
            $totalDuration,
            $appointment->id
        );

        if ($conflictMessage !== null) {
            return response()->json(['message' => $conflictMessage,], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Update appointment
        |--------------------------------------------------------------------------
        */
        $appointment->update([
            'staff_id' => $staff->id,
            'start_datetime' => $start,
            'end_datetime' => $end,
        ]);

        return response()->json([
            'message' => 'Appointment rescheduled successfully.',
            'appointment' => $appointment->fresh([
                'customer',
                'staff',
                'appointmentServices.service',
            ]),
        ]);
    }
}

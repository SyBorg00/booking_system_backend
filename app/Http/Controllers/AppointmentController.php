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




    public function store(
        Request $request,
        AvailabilityService $availabilityService
    ) {

        //verification request are put in here instead of creating another request file
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

        //Retrieve business
        $business = Business::findOrFail($validated['business_id']);

        //verify that the customer belongs to the business 
        $customer = Customer::where('id', $validated['customer_id'])
            ->where('business_id', $business->id)
            ->first();

        if (!$customer) {
            throw ValidationException::withMessages([
                'customer_id' => ['The selected customer does not belong to this business.',],
            ]);
        }

        //verify that the staff belongs to the business
        $staff = Staff::where('id', $validated['staff_id'])
            ->where('business_id', $business->id)
            ->first();

        if (!$staff) {
            throw ValidationException::withMessages([
                'staff_id' => ['The selected staff member does not belong to this business.'],
            ]);
        }




        //Retrieve services and verify that they belong to the business
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


        //Calculate total appointment duration
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


        //Check staff availability
        $availableSlots = $availabilityService->getAvailableSlotsForServices(
            $staff,
            $services,
            $start->toDateString()
        );

        $requestedSlotIsAvailable = collect($availableSlots)
            ->contains(function ($slot) use ($start, $end) {
                $slotStart = Carbon::parse($start->toDateString() . ' ' . $slot['start']);
                $slotEnd = Carbon::parse($start->toDateString() . ' ' . $slot['end']);
                return $slotStart->equalTo($start) && $slotEnd->equalTo($end);
            });

        if (!$requestedSlotIsAvailable) {
            throw ValidationException::withMessages([
                'start_datetime' => [
                    'The selected time is not available for this staff member.'
                ],
            ]);
        }


        //create appointment and service snapshots
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

    //only update the status and notes; ignore everything else, as they are not allowed to be updated after creation (FOR NOW)
    public function update(Request $request, Appointment $appointment)
    {
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
}

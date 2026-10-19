<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{

    //Get available appointment slots for a staff member. (NOT USED AS OF THE MOMENT)
    public function getAvailableSlots(
        Staff $staff,
        Service $service,
        string $date
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Verify that the staff member provides this service.
        |--------------------------------------------------------------------------
        */
        if (!$this->staffProvidesServices($staff, collect([$service]))) {
            return [];
        }
        $date = Carbon::parse($date);




        //The requested day's boundaries.
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->addDay()->startOfDay();

        /*
         * Laravel's Carbon dayOfWeek:
         *
         * 0 = Sunday
         * 1 = Monday
         * ...
         * 6 = Saturday
         * just in case one is too lazy to scavenge for notes
         */
        $dayOfWeek = $date->dayOfWeek;

        //Get the staff member's working hours for this particular day.
        $staffHours = $staff->hours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_off', false)
            ->orderBy('start_time')
            ->get();

        if ($staffHours->isEmpty()) {
            return [];
        }


        //Get all time-off records that overlap with the requested day.
        $timeOffs = $staff->timeOffs()
            ->where('start_datetime', '<', $dayEnd)
            ->where('end_datetime', '>', $dayStart)
            ->get();


        //Get appointments that actually block the staff member's schedule. pending and confirmed appointments are considered blocking.
        $appointments = Appointment::where('staff_id', $staff->id)
            ->where('start_datetime', '<', $dayEnd)
            ->where('end_datetime', '>', $dayStart)
            ->whereIn('status', [
                'pending',
                'confirmed',
            ])
            ->get();


        //Calculate how long the requested service occupies the staff member.

        /* Example:
         * duration = 60
         * buffer   = 15

         * total = 75 minutes
         */
        $slotDuration =
            $service->duration_minutes +
            $service->buffer_minutes;

        if ($slotDuration <= 0) {
            return [];
        }

        $availableSlots = [];


        //Generate slots for each working period.
        foreach ($staffHours as $hours) {

            $periodStart = Carbon::parse(
                $date->toDateString() . ' ' . $hours->start_time
            );

            $periodEnd = Carbon::parse(
                $date->toDateString() . ' ' . $hours->end_time
            );


            //Generate candidate slots
            $slot = $periodStart->copy();

            while (
                $slot->copy()
                ->addMinutes($slotDuration)
                ->lte($periodEnd)
            ) {
                $slotEnd = $slot->copy()
                    ->addMinutes($slotDuration);


                //Check time-off conflicts.
                $conflictsWithTimeOff = $timeOffs->contains(
                    function ($timeOff) use ($slot, $slotEnd) {
                        return $slot->lt($timeOff->end_datetime)
                            && $slotEnd->gt($timeOff->start_datetime);
                    }
                );


                //Check confirmed/pending appointment conflicts.
                $conflictsWithAppointment = $appointments->contains(
                    function ($appointment) use ($slot, $slotEnd) {
                        return $slot->lt($appointment->end_datetime)
                            && $slotEnd->gt($appointment->start_datetime);
                    }
                );

                //Only return slots that do not conflict with anything.
                if (
                    !$conflictsWithTimeOff &&
                    !$conflictsWithAppointment
                ) {
                    $availableSlots[] = [
                        'start' => $slot->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                    ];
                }

                //Move to the next candidate slot. As of the moment, its just hardcoded to 15 minutes, though this should be business based dependent
                $slot->addMinutes(15);
            }
        }

        return $availableSlots;
    }

    // Similar to the one above but for multiple services. (CURRENTLY IN USE)
    public function getAvailableSlotsForServices(
        Staff $staff,
        Collection $services,
        string $date
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Verify that the staff member provides all requested services.
        |--------------------------------------------------------------------------
        */
        if (!$this->staffProvidesServices($staff, $services)) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Fetch Business data and their respective timezone
        |--------------------------------------------------------------------------
        */
        $business = Business::findOrFail($staff->business_id);
        $timezone = $business->timezone;

        // Interpret the requested date in the business timezone.
        $localDate = Carbon::parse($date, $timezone)->startOfDay();

        $dayOfWeek = $localDate->dayOfWeek;

        $staffHours = $staff->hours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_off', false)
            ->orderBy('start_time')
            ->get();

        if ($staffHours->isEmpty()) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Calculate total duration, including service buffers.
        |--------------------------------------------------------------------------
        */
        $slotDuration = $services->sum(function ($service) {
            return $service->duration_minutes
                + $service->buffer_minutes;
        });

        if ($slotDuration <= 0) {
            return [];
        }

        $availableSlots = [];

        /*
        |--------------------------------------------------------------------------
        | Generate candidate slots for each working period.
        |--------------------------------------------------------------------------
        */
        foreach ($staffHours as $hours) {
            $periodStart = Carbon::parse(
                $localDate->toDateString() . ' ' . $hours->start_time,
                $timezone
            );

            $periodEnd = Carbon::parse(
                $localDate->toDateString() . ' ' . $hours->end_time,
                $timezone
            );

            $slot = $periodStart->copy();

            while (
                $slot->copy()
                    ->addMinutes($slotDuration)
                    ->lte($periodEnd)
            ) {
                /*
                |--------------------------------------------------------------------------
                | Convert the candidate start to UTC before validation.
                |--------------------------------------------------------------------------
                */
                $slotStartUtc = $slot->copy()->utc();

                /*
                |--------------------------------------------------------------------------
                | Validate the candidate using the shared validation method.
                |--------------------------------------------------------------------------
                */
                $conflictMessage = $this->validateSlot(
                    $staff,
                    $slotStartUtc,
                    $slotDuration
                );

                /*
                |--------------------------------------------------------------------------
                | Return only valid slots in the business's local time.
                |--------------------------------------------------------------------------
                */
                if ($conflictMessage === null) {
                    $slotEndLocal = $slot->copy()
                        ->addMinutes($slotDuration);

                    $availableSlots[] = [
                        'start' => $slot->format('H:i'),
                        'end' => $slotEndLocal->format('H:i'),
                    ];
                }
                // Advance to the next candidate slot.
                $slot->addMinutes(15);
            }
        }

        return $availableSlots;
    }


    /*
    |-----------------------------------------------------------------------------------------
    | Helper function to check if a staff member provides all the requested services.
    |------------------------------------------------------------------------------------------
    | Will be used in the appointment creation and rescheduling process to ensure that 
    | the staff member can actually provide all the requested services.
    |-----------------------------------------------------------------------------------------
    */
    private function staffProvidesServices(
        Staff $staff,
        Collection $services
    ): bool {
        $serviceIds = $services
            ->pluck('id')
            ->unique();

        if ($serviceIds->isEmpty()) {
            return false;
        }

        $assignedServiceCount = $staff->services()
            ->whereIn('services.id', $serviceIds)
            ->count();

        return $assignedServiceCount === $serviceIds->count();
    }


    /*
    |=====================================================================================================================================================
    |    *Helper function to validate the availabale time slots of an appointment. Ensure that the following are in sync on what validation method to use:
    |    * 1. Availability Generation  (getAvailableSlotsForServices() -> this class)
    |    * 2. Appointment creation (store() -> AppointmentController)
    |    * 3. Appointment rescheduling  (reschedule() -> AppointmentController)
    |=====================================================================================================================================================
    */
    public function validateSlot(
        Staff $staff,
        Carbon $start,
        int $durationMinutes,
        ?int $excludeAppointmentId = null //This guy is only used for the rescheduling function in the appointment controller
    ): ?string {

        /*
        |--------------------------------------------------------------------------
        | Fetch business data and its timezone
        |--------------------------------------------------------------------------
        */
        $business = Business::findOrFail($staff->business_id);
        $timezone = $business->timezone;

        /*
        |--------------------------------------------------------------------------
        | Normalize the incoming timestamp to UTC.
        |--------------------------------------------------------------------------
        */
        $startUtc = $start->copy()->utc();
        $endUtc = $startUtc->copy()->addMinutes($durationMinutes);

        //disregard
        $end = $start->copy()->addMinutes($durationMinutes);

        /*
        |--------------------------------------------------------------------------
        | Determine the relevant local working day.
        |--------------------------------------------------------------------------
        */
        $localStart = $startUtc->copy()->setTimezone($timezone);
        $localDate = $localStart->toDateString();
        $dayOfWeek = $localStart->dayOfWeek;

        /*
        |--------------------------------------------------------------------------
        | Check working hours
        |--------------------------------------------------------------------------
        */
        //disregard
        $dayOfWeek = $start->dayOfWeek;

        $staffHours = $staff->hours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_off', false)
            ->get();

        $fitsWorkingHours = $staffHours->contains(
            function ($hours) use (
                $localDate,
                $timezone,
                $startUtc,
                $endUtc,
        ) {

                $periodStartUtc = Carbon::parse(
                    $localDate . ' ' . $hours->start_time,
                    $timezone
                )->utc();

                $periodEndUtc = Carbon::parse(
                    $localDate . ' ' . $hours->end_time,
                    $timezone
                )->utc();

                return $startUtc->gte($periodStartUtc)
                && $endUtc->lte($periodEndUtc);
            }
        );

        if (!$fitsWorkingHours) {
            return 'The selected time is outside the staff member\'s working hours.';
        }

        /*
        |--------------------------------------------------------------------------
        | Check time-offs
        |--------------------------------------------------------------------------
        */

        $timeOffConflict = $staff->timeOffs()
            ->where(
                'start_datetime',
                '<',
                $endUtc->format('Y-m-d H:i:s')
            )
            ->where(
                'end_datetime',
                '>',
                $startUtc->format('Y-m-d H:i:s')
            )
            ->exists();

        if ($timeOffConflict) {
            return 'The selected time conflicts with the staff member\'s time off.';
        }

        /*
        |--------------------------------------------------------------------------
        | Check existing appointments
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Excluded the appointment currently being rescheduled.
        |
        */
        $appointmentQuery = Appointment::where('staff_id', $staff->id)
            ->where(
                'start_datetime',
                '<',
                $endUtc->format('Y-m-d H:i:s')
            )
            ->where(
                'end_datetime',
                '>',
                $startUtc->format('Y-m-d H:i:s')
            )
            ->whereIn('status', ['pending', 'confirmed']);

        /*
        |--------------------------------------------------------------------------
        | Exclude the appointment currently being rescheduled
        |--------------------------------------------------------------------------
        */
        if ($excludeAppointmentId !== null) {
            $appointmentQuery->where(
                'id',
                '!=',
                $excludeAppointmentId
            );
        }

        if ($appointmentQuery->exists()) {
            return 'The selected time conflicts with another appointment.';
        }

        return null;
    }
}

<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{

    //Get available appointment slots for a staff member.
    public function getAvailableSlots(
        Staff $staff,
        Service $service,
        string $date
    ): array {
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


    // Similar to the one above but for multiple services.
    public function getAvailableSlotsForServices(
        Staff $staff,
        Collection $services,
        string $date
    ): array {
        $date = Carbon::parse($date);

        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->addDay()->startOfDay();

        $dayOfWeek = $date->dayOfWeek;

        $staffHours = $staff->hours()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_off', false)
            ->orderBy('start_time')
            ->get();

        if ($staffHours->isEmpty()) {
            return [];
        }

        $timeOffs = $staff->timeOffs()
            ->where('start_datetime', '<', $dayEnd)
            ->where('end_datetime', '>', $dayStart)
            ->get();

        $appointments = Appointment::where('staff_id', $staff->id)
            ->where('start_datetime', '<', $dayEnd)
            ->where('end_datetime', '>', $dayStart)
            ->whereIn('status', [
                'pending',
                'confirmed',
            ])
            ->get();


        /*
        This is where it differs from the other method

        * Calculate the total amount of time required by all services.
        *
        * Example:
        *
        * Service 1:
        * duration = 30
        * buffer   = 15
        *
        * Service 2:
        * duration = 60
        * buffer   = 20
        *
        * Total = 125 minutes
        */
        $slotDuration = $services->sum(function ($service) {
            return $service->duration_minutes
                + $service->buffer_minutes;
        });

        if ($slotDuration <= 0) {
            return [];
        }

        $availableSlots = [];

        // Generate slots for each working period.
        foreach ($staffHours as $hours) {

            $periodStart = Carbon::parse(
                $date->toDateString() . ' ' . $hours->start_time
            );

            $periodEnd = Carbon::parse(
                $date->toDateString() . ' ' . $hours->end_time
            );

            // Generate candidate slots.
            $slot = $periodStart->copy();

            while (
                $slot->copy()
                ->addMinutes($slotDuration)
                ->lte($periodEnd)
            ) {
                $slotEnd = $slot->copy()
                    ->addMinutes($slotDuration);

                // Check time-off conflicts.
                $conflictsWithTimeOff = $timeOffs->contains(
                    function ($timeOff) use ($slot, $slotEnd) {
                        return $slot->lt($timeOff->end_datetime)
                            && $slotEnd->gt($timeOff->start_datetime);
                    }
                );

                // Check confirmed/pending appointment conflicts.
                $conflictsWithAppointment = $appointments->contains(
                    function ($appointment) use ($slot, $slotEnd) {
                        return $slot->lt($appointment->end_datetime)
                            && $slotEnd->gt($appointment->start_datetime);
                    }
                );

                // Only return slots that do not conflict with anything.
                if (
                    !$conflictsWithTimeOff &&
                    !$conflictsWithAppointment
                ) {
                    $availableSlots[] = [
                        'start' => $slot->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                    ];
                }

                // Move to the next candidate slot.
                $slot->addMinutes(15);
            }
        }

        return $availableSlots;
    }
}

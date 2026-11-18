<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBusinessHourRequest;
use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Support\Facades\DB;

class BusinessHourController extends Controller
{
    // Retrieve the complete weekly schedule.
    public function index(Business $business)
    {
        $this->authorize('viewAny', [
            BusinessHour::class,
            $business,
        ]);

        return response()->json([
            'data' => $business->businessHours()
                ->orderBy('day_of_week')
                ->get(),
        ]);
    }

    // Update the complete weekly schedule.
    public function update(
        UpdateBusinessHourRequest $request,
        Business $business
    ) {
        $validated = $request->validated();

        DB::transaction(function () use ($business, $validated) {

            foreach ($validated['business_hours'] as $hours) {

                $isClosed = (bool) $hours['is_closed'];

                $business->businessHours()->updateOrCreate(
                    [
                        'day_of_week' => $hours['day_of_week'],
                    ],
                    [
                        'open_time' => $isClosed
                            ? null
                            : $hours['open_time'],

                        'close_time' => $isClosed
                            ? null
                            : $hours['close_time'],

                        'is_closed' => $isClosed,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Business hours updated successfully.',
            'data' => $business->businessHours()
                ->orderBy('day_of_week')
                ->get(),
        ]);
    }
}

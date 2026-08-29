<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\StaffHour;

class UpdateStaffHourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => [
                'sometimes',
                'integer',
                'between:0,6',
            ],
            'start_time' => [
                'sometimes',
                'nullable',
                'date_format:H:i',
            ],
            'end_time' => [
                'sometimes',
                'nullable',
                'date_format:H:i',
                'after:start_time',
            ],
            'is_off' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {

                $staffHour = $this->route('staffHour');
                if (!$staffHour) {
                    return;
                }

                $dayOfWeek = $this->input(
                    'day_of_week',
                    $staffHour->day_of_week
                );

                $startTime = $this->input(
                    'start_time',
                    $staffHour->start_time
                );

                $endTime = $this->input(
                    'end_time',
                    $staffHour->end_time
                );

                $isOff = $this->has('is_off') ? $this->boolean('is_off') : $staffHour->is_off;

                if ($isOff) {
                    return;
                }

                $overlapExists = StaffHour::where(
                    'staff_id',
                    $staffHour->staff_id
                )
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_off', false)
                    ->where('id', '!=', $staffHour->id)
                    ->where(function ($query) use ($startTime, $endTime) {
                        $query
                            ->where('start_time', '<', $endTime)
                            ->where('end_time', '>', $startTime);
                    })->exists();

                if ($overlapExists) {
                    $validator->errors()->add(
                        'start_time',
                        'This working period overlaps with an existing staff schedule'
                    );
                }
            }
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\StaffTimeOff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateStaffTimeOffRequest extends FormRequest
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
            'start_datetime' => [
                'sometimes',
                'date',
            ],

            'end_datetime' => [
                'sometimes',
                'date',
                'after:start_datetime',
            ],

            'reason' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    //similar process to the store request, but to exclude the record itself
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $timeOff = $this->route('staffTimeOff');

                if (!$timeOff) {
                    return;
                }

                $startDatetime = $this->input(
                    'start_datetime',
                    $timeOff->start_datetime
                );

                $endDatetime = $this->input(
                    'end_datetime',
                    $timeOff->end_datetime
                );

                if (!$startDatetime || !$endDatetime) {
                    return;
                }

                $overlapExists = StaffTimeOff::where(
                    'staff_id',
                    $timeOff->staff_id
                )
                    ->where('id', '!=', $timeOff->id)
                    ->where(function ($query) use (
                        $startDatetime,
                        $endDatetime
                    ) {
                        $query
                            ->where(
                                'start_datetime',
                                '<',
                                $endDatetime
                            )
                            ->where(
                                'end_datetime',
                                '>',
                                $startDatetime
                            );
                    })
                    ->exists();

                if ($overlapExists) {
                    $validator->errors()->add(
                        'start_datetime',
                        'This time-off period overlaps with an existing time-off period.'
                    );
                }
            },
        ];
    }
}

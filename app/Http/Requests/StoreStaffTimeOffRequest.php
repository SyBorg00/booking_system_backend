<?php

namespace App\Http\Requests;

use App\Models\StaffTimeOff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStaffTimeOffRequest extends FormRequest
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
                'required',
                'date',
            ],

            'end_datetime' => [
                'required',
                'date',
                'after:start_datetime',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    //the validation part of the process
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (
                    !$this->start_datetime ||
                    !$this->end_datetime
                ) {
                    return;
                }

                $staff = $this->route('staff');

                $overlapExists = StaffTimeOff::where(
                    'staff_id',
                    $staff->id
                )
                    ->where(function ($query) {
                        $query
                            ->where(
                                'start_datetime',
                                '<',
                                $this->end_datetime
                            )
                            ->where(
                                'end_datetime',
                                '>',
                                $this->start_datetime
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

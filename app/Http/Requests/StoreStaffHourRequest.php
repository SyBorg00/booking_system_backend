<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\StaffHour;

class StoreStaffHourRequest extends FormRequest
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
                'required',
                'integer',
                'between:0,6',
            ],
            'start_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'end_time' => [
                'nullable',
                'date_format:H:i',
                'after:start_time',
            ],
            'is_off' => [
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {

                //if the is_off boolean is true, ignore the checking
                if ($this->boolean('is_off')) {
                    return;
                }

                //if there are no records of the start/end time, ignore the checking
                if (!$this->start_time || !$this->end_time) {
                    return;
                }

                $staff = $this->route('staff');

                //process: loads the existing staff hour records of a specific staff member, then tries to compare with the inserted data if there is an overlap
                //between those existing time records
                $overlapExists = StaffHour::where('staff_id', $staff->id)
                    ->where('day_of_week', $this->day_of_week)
                    ->where('is_off', false)
                    ->where(function ($query) {
                        $query
                            ->where('start_time', '<', $this->end_time)
                            ->where('end_time', '>', $this->start_time);
                    })
                    ->exists();

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

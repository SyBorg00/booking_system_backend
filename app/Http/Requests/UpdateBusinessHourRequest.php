<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\BusinessHour;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;


class UpdateBusinessHourRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $business instanceof Business
            && $this->user()->can('updateSchedule', [
                BusinessHour::class,
                $business,
            ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'business_hours' => [
                'required',
                'array',
                'size:7',
            ],

            'business_hours.*.day_of_week' => [
                'required',
                'integer',
                'between:0,6',
                'distinct',
            ],

            'business_hours.*.open_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'business_hours.*.close_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'business_hours.*.is_closed' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->input('business_hours', []) as $index => $hours) {

                    $isClosed = filter_var(
                        $hours['is_closed'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    );

                    if ($isClosed) {
                        continue;
                    }

                    if (empty($hours['open_time'])) {
                        $validator->errors()->add(
                            "business_hours.$index.open_time",
                            'Opening time is required when the business is open.'
                        );
                    }

                    if (empty($hours['close_time'])) {
                        $validator->errors()->add(
                            "business_hours.$index.close_time",
                            'Closing time is required when the business is open.'
                        );
                    }

                    if (
                        !empty($hours['open_time']) &&
                        !empty($hours['close_time']) &&
                        $hours['close_time'] <= $hours['open_time']
                    ) {
                        $validator->errors()->add(
                            "business_hours.$index.close_time",
                            'Closing time must be later than opening time.'
                        );
                    }
                }
            },
        ];
    }
}

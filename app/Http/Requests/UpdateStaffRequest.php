<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('business');
        $staff = $this->route('staff');

        if (
            !$business instanceof \App\Models\Business ||
            !$staff instanceof \App\Models\Staff
        ) {
            return false;
        }

        if ($staff->business_id !== $business->id) {
            return false;
        }

        return $this->user()->can('update', $staff);
}

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'max:255',
            ],

            'last_name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'position' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}

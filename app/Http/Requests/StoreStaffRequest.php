<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $business instanceof \App\Models\Business
            && $this->user()->can('create', [
                \App\Models\Staff::class,
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
            //the staff do be needing that the user exist (doesn't make sense for a staff member to not have a user account, unlike a customer)

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                
                // Ensure the user_id is unique in the staff table
                Rule::unique('staff', 'user_id'),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'position' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }
}

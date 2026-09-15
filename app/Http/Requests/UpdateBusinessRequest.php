<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $business = $this->route('business');

        return [
            [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255'
                ],
                'slug' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('businesses', 'slug')
                        ->ignore($business->id),
                ],
                'description' => [
                    'sometimes',
                    'nullable',
                    'string'
                ],
                'currency' => [
                    'sometimes',
                    'required',
                    'string',
                    'size:3'
                ],
                'phone' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:50'
                ],
                'email' => [
                    'sometimes',
                    'nullable',
                    'email',
                    'max:255'
                ],
                'address' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:500'
                ],
                'timezone' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100'
                ],
                'logo' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255'
                ],
                'status' => [
                    'sometimes',
                    'required',
                    'in:active,inactive'
                ],
            ]
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('business');
        $service = $this->route('service');

        if (
            !$business instanceof \App\Models\Business ||
            !$service instanceof \App\Models\Service
        ) {
            return false;
        }

        if ($service->business_id !== $business->id) {
            return false;
        }

        return $this->user()->can('update', $service);
    }
    

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],

            'duration_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],

            'buffer_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:active,inactive',
            ],
        ];
    }
}

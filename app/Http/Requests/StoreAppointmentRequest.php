<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_id' => [
                'required',
                'integer',
                'exists:businesses,id',
            ],

            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],

            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
            ],

            'start_datetime' => [
                'required',
                'date',
            ],

            'services' => [
                'required',
                'array',
                'min:1',
            ],

            'services.*.service_id' => [
                'required',
                'integer',
                'distinct',
                'exists:services,id',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $businessId = $this->input('business_id');
            $staffId = $this->input('staff_id');

            $serviceIds = collect($this->input('services', []))
                ->pluck('service_id')
                ->unique()
                ->values();

            if (!$businessId || !$staffId || $serviceIds->isEmpty()) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Check that all services belong to the business
            |--------------------------------------------------------------------------
            */
            $businessServiceIds = Service::where('business_id', $businessId)
                ->whereIn('id', $serviceIds)
                ->pluck('id');

            $invalidBusinessServices = $serviceIds->diff(
                $businessServiceIds
            );

            if ($invalidBusinessServices->isNotEmpty()) {
                $validator->errors()->add(
                    'services',
                    'One or more selected services do not belong to this business.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Check that the staff provides all selected services
            |--------------------------------------------------------------------------
            */
            $staff = Staff::find($staffId);

            if (!$staff || $staff->business_id != $businessId) {
                return;
            }

            $assignedServiceIds = $staff->services()
                ->whereIn('services.id', $serviceIds)
                ->pluck('services.id');

            $unassignedServiceIds = $serviceIds->diff(
                $assignedServiceIds
            );

            if ($unassignedServiceIds->isNotEmpty()) {
                $validator->errors()->add(
                    'services',
                    'One or more selected services are not assigned to this staff member.'
                );
            }
        });
    }
}

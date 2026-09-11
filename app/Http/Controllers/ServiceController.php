<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Service;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;

class ServiceController extends Controller
{
    //obtain the list of services for a specific business
    public function index(Business $business)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing the list of services
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        return response()->json(
            $business->services()->get()
        );
    }

    //create and store service
    public function store(
        StoreServiceRequest $request,
        Business $business
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a service
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Use the relationship between service and business to create said service 
        | for the specific business, rather than using Service::create. 
        | This is really useful for multi-business architecture.
        |--------------------------------------------------------------------------
        */
        $service = $business->services()->create(
            $request->validated()
        );

        return response()->json(
            $service,
            201
        );
    }

    //show service detail
    public function show(
        Business $business,
        Service $service
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing a specific service
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Ensure that the service belongs to the business before operating this, if not return 404
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $service->business_id === $business->id,
            404
        );
        return response()->json($service);
    }

    //update service
    public function update(
        UpdateServiceRequest $request,
        Business $business,
        Service $service
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before updating a service
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Ensure that the service belongs to the business before operating this, if not return 404
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $service->business_id === $business->id,
            404
        );
        $service->update(
            $request->validated()
        );

        return response()->json($service);
    }

    //delete service
    public function destroy(
        Business $business,
        Service $service
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Ensure that the service belongs to the business before operating this, if not return 404
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $service->business_id === $business->id,
            404
        );

        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
        ]);
    }
}

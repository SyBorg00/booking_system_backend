<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Service;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;

class ServiceController extends Controller
{
    public function index(Business $business)
    {
        return response()->json(
            $business->services()->get()
        );
    }

    //create and store service
    public function store(
        StoreServiceRequest $request,
        Business $business
    ) {
        //rather than using Service::create, we use the relationship to create the service for the specific business
        // (really useful for multi-business architecture)
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
        //this is to ensure that the service being shown belongs to that specific business, otherwise return 404 and abort this function
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
        //same case with show() but for updating
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
        //same case but for deleting
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

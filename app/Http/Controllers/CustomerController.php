<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Customer;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;

class CustomerController extends Controller
{

    //obtain the list of customers for a specific business
    public function index(Business $business)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the customers
        |--------------------------------------------------------------------------
        */
        $this->authorize('viewAny', [Customer::class, $business]);

        return response()->json(
            [
                'data' => $business->customers()->get()
            ]
        );
    }

    //create a new customer for a specific business
    public function store(
        StoreCustomerRequest $request,
        Business $business
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to create a customer for the business
        |--------------------------------------------------------------------------
        */
        $this->authorize('create', [Customer::class, $business]);

        /*
        |--------------------------------------------------------------------------
        | Put business_id in the request data to ensure that the customer is associated with the correct business
        |--------------------------------------------------------------------------
        */
        $customer = $business->customers()->create(
            $request->validated()
        );

        return response()->json(
            [
                'message' => "Customer created successfully.",
                'data' => $customer
            ],
            201
        );
    }

    //show a specific customer for a specific business
    public function show(
        Business $business,
        Customer $customer
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $customer);

        /*
        |--------------------------------------------------------------------------
        | Perform ownership check to ensure that the customer belongs to the business, if not return 404  
        |--------------------------------------------------------------------------
        */
        $customer = $business->customers()
            ->whereKey($customer->id)
            ->firstOrFail();

        

        return response()->json([
            "data" => $customer
        ]);
    }

    //update a specific customer for a specific business
    public function update(
        UpdateCustomerRequest $request,
        Business $business,
        Customer $customer
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to update the customer before proceeding
        |--------------------------------------------------------------------------
        */
        $this->authorize('update', $customer);

        /*
        |--------------------------------------------------------------------------
        | Perform ownership check to ensure that the customer belongs to the business, if not return 404  
        |--------------------------------------------------------------------------
        */
        $customer = $business->customers()
            ->whereKey($customer->id)
            ->firstOrFail();


        $customer->update(
            $request->validated()
        );

        return response()->json(
            [
                'message' => 'Customer updated successfully.',
                'data' => $customer->fresh()
            ]
        );
    }

    //delete a specific customer for a specific business
    public function destroy(
        Business $business,
        Customer $customer
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to delete the customer before proceeding
        |--------------------------------------------------------------------------
        */
        $this->authorize('delete', $customer);

        /*
        |--------------------------------------------------------------------------
        | Perform ownership check to ensure that the customer belongs to the business, if not return 404  
        |--------------------------------------------------------------------------
        */
        $customer = $business->customers()
            ->whereKey($customer->id)
            ->firstOrFail();

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}

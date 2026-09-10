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
        | Authorization: Ensure that the user has permission to view the business before fetching customers
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        return response()->json(
            $business->customers()->get()
        );
    }

    //create a new customer for a specific business
    public function store(
        StoreCustomerRequest $request,
        Business $business
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Put business_id in the request data to ensure that the customer is associated with the correct business
        |--------------------------------------------------------------------------
        */
        $customer = $business->customers()->create(
            $request->validated()
        );

        return response()->json(
            $customer,
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
        | Authorization: Ensure that the user has permission to view the business before creating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Ensure that the customer belongs to the business before operating this, if not return 404
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $customer->business_id === $business->id,
            404
        );

        return response()->json($customer);
    }

    //update a specific customer for a specific business
    public function update(
        UpdateCustomerRequest $request,
        Business $business,
        Customer $customer
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Ensure that the customer belongs to the business before operating this, if not return 404
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $customer->business_id === $business->id,
            404
        );

        $customer->update(
            $request->validated()
        );

        return response()->json(
            $customer->fresh()
        );
    }

    //delete a specific customer for a specific business
    public function destroy(
        Business $business,
        Customer $customer
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before creating a customer
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        /*
        |--------------------------------------------------------------------------
        | Ensure that the customer belongs to the business before operating this, if not return 404
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $customer->business_id === $business->id,
            404
        );

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully.',
        ]);
    }
}

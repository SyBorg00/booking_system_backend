<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Models\Business;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing the list of businesses
        |--------------------------------------------------------------------------
        */
        $this->authorize('viewAny', Business::class);

        $user = auth()->user();
        $query = Business::query();

        if ($user->role === 'admin') {
            // Only businesses assigned to this admin.
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });

        } elseif ($user->role === 'staff') {
            // Only the business associated with this staff member.
            $query->whereHas('staff', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });

        } elseif ($user->role !== 'super_admin') {
            abort(403, 'Unauthorized.');
        }
        
        $businesses = $query
        ->orderBy('name')
        ->get();

        return response()->json([
            'data' => $businesses,
        ]);
    }

    public function store(StoreBusinessRequest $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to create a business before proceeding with the creation
        |--------------------------------------------------------------------------
        */
        $this->authorize('create', Business::class);

        $validated = $request->validated();

        $business = Business::create($validated);

        return response()->json([
            'message' => 'Business created successfully.',
            'data' => $business,
        ], 201);
    }

    public function show(Business $business)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to view the business before showing the business details
        |--------------------------------------------------------------------------
        */
        $this->authorize('view', $business);

        return response()->json([
            'data' => $business,
        ]);
    }

    public function update(
        UpdateBusinessRequest $request,
        Business $business
    ) {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to update the business before proceeding with the update
        |--------------------------------------------------------------------------
        */
        $this->authorize('update', $business);

        $business->update($request->validated());

        return response()->json([
            'message' => 'Business updated successfully.',
            'data' => $business->fresh(),
        ]);
    }

    public function destroy(Business $business)
    {
        /*
        |--------------------------------------------------------------------------
        | Authorization: Ensure that the user has permission to delete the business before proceeding with the deletion
        |--------------------------------------------------------------------------
        */
        $this->authorize('delete', $business);

        $business->delete();

        return response()->json([
            'message' => 'Business deleted successfully.',
        ]);
    }
}

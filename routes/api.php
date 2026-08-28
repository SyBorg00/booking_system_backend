<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StaffController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API is working!',
        'status' => 'success'
    ]);
});


//
Route::prefix('businesses/{business}')->group(function () {

    /*==================
    SERVICES SECTION 
    ====================*/

    //show the multiple services for a specific business
    Route::get('services', [ServiceController::class, 'index']);

    //create a new service for a specific business
    Route::post('services', [ServiceController::class, 'store']);

    //show a specific service for a specific business
    Route::get('services/{service}', [ServiceController::class, 'show']);

    //update a specific service for a specific business
    Route::put('services/{service}', [ServiceController::class, 'update']);

    //delete a specific service for a specific business
    Route::delete('services/{service}', [ServiceController::class, 'destroy']);

    /*==================
    CUSTOMERS SECTION 
    ====================*/

    //show the multiple customers for a specific business
    Route::get('customers', [CustomerController::class, 'index']);

    //create a new customer for a specific business
    Route::post('customers', [CustomerController::class, 'store']);

    //show a specific customer for a specific business
    Route::get('customers/{customer}', [CustomerController::class, 'show']);

    //update a specific customer for a specific business
    Route::put('customers/{customer}', [CustomerController::class, 'update']);

    //delete a specific customer for a specific business
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);

    /*==================
    STAFF SECTION 
    ====================*/
    //fetch the list of staff for a specific business
    Route::get('staff', [StaffController::class, 'index']);

    //create a new staff member for a specific business
    Route::post('staff', [StaffController::class, 'store']);

    //show a specific staff member for a specific business
    Route::get('staff/{staff}', [StaffController::class, 'show']);

    //update a specific staff member for a specific business
    Route::put('staff/{staff}', [StaffController::class, 'update']);

    //delete a specific staff member for a specific business
    Route::delete('staff/{staff}', [StaffController::class, 'destroy']);
});

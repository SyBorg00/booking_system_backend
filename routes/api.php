<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffHourController;
use App\Http\Controllers\StaffTimeOffController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\AppointmentController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'Laravel API is working!',
        'status' => 'success'
    ]);
});


/*========================
BUSINESS PREFIX SECTION 
==========================*/
Route::prefix('businesses/{business}')->group(function () {

    /*==================
    SERVICES SUB-SECTION 
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
    CUSTOMERS SUB-SECTION 
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
    STAFF SUB-SECTION 
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

    /*==================
    STAFF HOUR SUB-SECTION 
    ====================*/

    //fetch the list of staff hours for a specific staff member
    Route::get('staff/{staff}/hours', [StaffHourController::class, 'index']);

    //create a new staff hour for a specific staff member
    Route::post('staff/{staff}/hours', [StaffHourController::class, 'store']);

    //show a specific staff hour for a specific staff member
    Route::get('staff/{staff}/hours/{staffHour}', [StaffHourController::class, 'show']);

    //update a specific staff hour for a specific staff member
    Route::put('staff/{staff}/hours/{staffHour}', [StaffHourController::class, 'update']);

    //delete a specific staff hour for a specific staff member
    Route::delete('staff/{staff}/hours/{staffHour}', [StaffHourController::class, 'destroy']);

    /*==================
    STAFF TIME-OFFS SUB-SECTION 
    ====================*/
    //fetch time-offs of a specific staff member
    Route::get('staff/{staff}/time-offs', [StaffTimeOffController::class, 'index']);

    //create a new staff time-off from a specific staff member
    Route::post('staff/{staff}/time-offs', [StaffTimeOffController::class, 'store']);

    //fetch a specific time-off record from a specific staff member
    Route::get('staff/{staff}/time-offs/{staffTimeOff}', [StaffTimeOffController::class, 'show']);

    //update a specific time-off record from a specific staff member
    Route::put('staff/{staff}/time-offs/{staffTimeOff}', [StaffTimeOffController::class, 'update']);

    //delete a specific time-off record from a specific staff member
    Route::delete('staff/{staff}/time-offs/{staffTimeOff}', [StaffTimeOffController::class, 'destroy']);
});

/*==================
AVAILABILITY SECTION
====================*/
// //fetching w/ only one service (will be commented as of the moment)
// Route::get('/businesses/{business}/availability', [AvailabilityController::class, 'index']);

//fetching w/ multiple services
Route::get('/businesses/{business}/availability', [AvailabilityController::class, 'generateSlots']);


/*==================
APPOINTMENT SECTION
====================*/
/* 
The reason there's no business prefix is because the business_id is included in the request body, so we don't need to include it in the URL (also it will subsequently
use the availability route anyways. and also this is for the customer anyways, they need global access)
*/

//fetch all appointments for a specific business (with optional filters)
Route::get('/appointments', [AppointmentController::class, 'index']);

//fetch a specific appointment by its ID
Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);

//create a new appointment for a specific business
Route::post('/appointments', [AppointmentController::class, 'store']);

//update status and/or notes of an appointment only (for now)
Route::patch('appointments/{appointment}', [AppointmentController::class, 'update']);

//reschedule an existing appointment
Route::patch('appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);

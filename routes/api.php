<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServiceController;

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
});

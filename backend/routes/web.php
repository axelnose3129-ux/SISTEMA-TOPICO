<?php

use App\Http\Controllers\ClinicController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')->group(function (): void {
    Route::get('csrf', fn () => response()->json(['token' => csrf_token()]));
    Route::post('login', [ClinicController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth')->group(function (): void {
        Route::get('state', [ClinicController::class, 'state']);
        Route::post('logout', [ClinicController::class, 'logout']);
        Route::post('password', [ClinicController::class, 'password']);
        Route::post('patients', [ClinicController::class, 'savePatient']);
        Route::put('patients/{id}', [ClinicController::class, 'savePatient'])->whereNumber('id');
        Route::post('users', [ClinicController::class, 'saveUser']);
        Route::put('users/{id}', [ClinicController::class, 'saveUser'])->whereNumber('id');
        Route::post('patient/requests', [ClinicController::class, 'createPatientRequest']);
        Route::post('requests', [ClinicController::class, 'createRequest']);
        Route::post('requests/{id}/refer', [ClinicController::class, 'referRequest'])->whereNumber('id');
        Route::post('requests/{id}/close', [ClinicController::class, 'closeRequest'])->whereNumber('id');
        Route::post('encounters', [ClinicController::class, 'encounter']);
        Route::post('medicines', [ClinicController::class, 'medicine']);
        Route::put('medicines/{id}', [ClinicController::class, 'medicine'])->whereNumber('id');
        Route::post('batches', [ClinicController::class, 'batch']);
        Route::post('transfers', [ClinicController::class, 'transfer']);
        Route::post('transfers/{id}/receive', [ClinicController::class, 'receive'])->whereNumber('id');
        Route::post('prescriptions', [ClinicController::class, 'prescription']);
        Route::post('dispense', [ClinicController::class, 'dispense']);
    });
});

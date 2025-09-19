<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Orion routes for User resource (CRUD)
Orion::resource('users', UserController::class);

// Custom route for validating credentials
Route::post('/validate-credentials', [UserController::class, 'validateCredentials']);

// Custom route to get user by email
Route::get('/users/email/{email}', [UserController::class, 'getUserByEmail']);
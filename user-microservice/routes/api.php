<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserProfileController;

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

// Orion routes for UserProfile resource (CRUD)
Orion::resource('user-profiles', UserProfileController::class);


// Legacy routes (to be deprecated)
Orion::resource('users', UserController::class);
Route::post('/validate-credentials', [UserController::class, 'validateCredentials']);
Route::get('/users/email/{email}', [UserController::class, 'getUserByEmail']);
<?php

use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;
use App\Http\Controllers\Api\UserProfileController;

Route::group([], function () {
    // Public routes for UserProfile resource (CRUD)
    Orion::resource('user-profiles', UserProfileController::class);
});
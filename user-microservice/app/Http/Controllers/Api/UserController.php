<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserRequest;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisablePagination;
use Orion\Concerns\DisableAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use DisablePagination, DisableAuthorization;

    /**
     * The model that the controller works with.
     */
    protected $model = User::class;

    /**
     * The resource that represents the model.
     */
    protected $resource = UserResource::class;

    /**
     * The request that handles validation.
     */
    protected $request = UserRequest::class;

    /**
     * The relations that are allowed to be included together with a resource.
     */
    protected $includes = [];

    /**
     * The attributes that are used for filtering.
     */
    protected $filterableBy = [
        'name',
        'email',
        'created_at',
    ];

    /**
     * The attributes that are used for sorting.
     */
    protected $sortableBy = [
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that are searched upon.
     */
    protected $searchableBy = [
        'name',
        'email',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected function fillable(): array
    {
        return [
            'name',
            'email',
            'password',
        ];
    }

    public function validateCredentials(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return new UserResource($user);
    }

    public function getUserByEmail(string $email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return new UserResource($user);
    }
}
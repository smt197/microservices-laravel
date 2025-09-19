<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Jobs\SendEmailJob;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserRequest;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisablePagination;
use Orion\Concerns\DisableAuthorization;

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
     * Perform actions after storing the entity.
     */
    protected function afterStore($request, $entity)
    {
        // Dispatch email job after user creation
        $emailData = [
            'to' => $entity->email,
            'subject' => 'Welcome to User Microservice',
            'body' => 'Your account has been successfully created.'
        ];
        SendEmailJob::dispatch($emailData);

        return $entity;
    }

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
}
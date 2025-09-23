<?php

namespace App\Http\Controllers\Api;

use App\Models\UserProfile;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class UserProfileController extends Controller
{
    use DisableAuthorization;

    protected $model = UserProfile::class;

    public function filterableBy(): array
    {
        return ['auth_user_id', 'name', 'email'];
    }

    public function searchableBy(): array
    {
        return ['name', 'email', 'bio'];
    }

    public function sortableBy(): array
    {
        return ['id', 'name', 'email', 'created_at', 'updated_at'];
    }

    public function includes(): array
    {
        return [];
    }

    public function alwaysIncludes(): array
    {
        return [];
    }

    public function aggregates(): array
    {
        return [];
    }
}

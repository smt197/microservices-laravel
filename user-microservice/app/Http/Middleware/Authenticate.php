<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Helpers\ResponseServer;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // API only - always return null to get 401 JSON response
        return null;
    }


    public function handle($request, Closure $next, ...$guards)
    {
        if ($jwt = $request->cookie('jwt')) {
            // Valider le JWT via l'authentificationService
            try {
                $response = Http::withHeaders([
                    'Cookie' => 'jwt=' . $jwt,
                    'Accept' => 'application/json'
                ])->get(env('AUTH_SERVICE_URL') . '/api/user-profiles');

                if ($response->successful()) {
                    $userData = $response->json();

                    // Créer un User temporaire pour Laravel Auth
                    $user = new \App\Models\User();
                    $user->id = $userData['id'];
                    $user->name = $userData['name'];
                    $user->email = $userData['email'];
                    $user->email_verified_at = $userData['email_verified_at'] ?? null;

                    // Définir l'utilisateur authentifié
                    \Illuminate\Support\Facades\Auth::setUser($user);

                    return $next($request);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Auth validation failed: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function unauthorization()
    {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function forbidden()
    {
        return response()->json(['message' => 'Forbidden'], 403);
    }
}

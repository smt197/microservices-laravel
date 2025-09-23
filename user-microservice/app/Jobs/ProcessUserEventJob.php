<?php

namespace App\Jobs;

use App\Models\UserProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUserEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $eventData;

    public function __construct(array $eventData)
    {
        $this->eventData = $eventData;
    }

    public function handle()
    {
        try {
            $eventType = $this->eventData['event_type'] ?? null;
            $userData = $this->eventData['data'] ?? null;

            if (!$eventType || !$userData) {
                Log::warning('Invalid event data received', ['event_data' => $this->eventData]);
                return;
            }

            switch ($eventType) {
                case 'created':
                    $this->handleUserCreated($userData);
                    break;

                case 'updated':
                    $this->handleUserUpdated($userData);
                    break;

                case 'verified':
                    $this->handleUserVerified($userData);
                    break;

                default:
                    Log::info('Unhandled user event type', ['event_type' => $eventType]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process user event', [
                'error' => $e->getMessage(),
                'event_data' => $this->eventData
            ]);
            throw $e;
        }
    }

    private function handleUserCreated(array $userData)
    {
        // 2. Create UserProfile with reference to auth service
        $userProfile = UserProfile::updateOrCreate(
            ['auth_user_id' => $userData['id']],
            [
                // Only profile-specific fields, no user data duplication
            ]
        );

        Log::info('User profile created from auth event', [
            'auth_user_id' => $userData['id'],
            'profile_id' => $userProfile->id
        ]);
    }

    private function handleUserUpdated(array $userData)
    {
        // UserProfile doesn't need update for basic user data
        // Only profile-specific updates would be needed here

        Log::info('User updated event received (no action needed)', [
            'auth_user_id' => $userData['id']
        ]);
    }

    private function handleUserVerified(array $userData)
    {
        // User verification stays in authentificationService
        // No local action needed

        Log::info('User verified event received (no action needed)', [
            'auth_user_id' => $userData['id']
        ]);
    }
}

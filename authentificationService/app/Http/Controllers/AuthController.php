<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $response = Http::post(env('USER_MICROSERVICE_URL') . '/api/users', [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Failed to create user in user-microservice', 'error' => $response->body()], $response->status());
        }

        $user = $response->json()['data'];

        // Generate verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user['id'],
                'hash' => sha1($user['email']),
            ]
        );

        // Dispatch email job
        $emailData = [
            'to' => $user['email'],
            'subject' => 'Verify Your Email Address',
            'body' => 'Please click the following link to verify your email: ' . $verificationUrl
        ];
        SendEmailJob::dispatch($emailData)->onQueue('send_email');

        return response()->json([
            'message' => 'User registered successfully. Please check your email to verify your account.',
            'user' => $user
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        // Call user-microservice to validate credentials
        $response = Http::post(env('USER_MICROSERVICE_URL') . '/api/validate-credentials', [
            'email' => $request->email,
            'password' => $request->password,
        ]);

        if ($response->failed()) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $userData = $response->json()['data'];

        // Check if email is verified
        if (empty($userData['email_verified_at'])) {
            // Re-send verification email
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $userData['id'],
                    'hash' => sha1($userData['email']),
                ]
            );
            $emailData = [
                'to' => $userData['email'],
                'subject' => 'Verify Your Email Address',
                'body' => 'You must verify your email before logging in. Please click the link to verify: ' . $verificationUrl
            ];
            SendEmailJob::dispatch($emailData)->onQueue('send_email');

            return response()->json(['message' => 'Email not verified. A new verification link has been sent to your email address.'], 403);
        }

        // Create a temporary user instance for token generation
        $user = new User();
        $user->id = $userData['id'];
        $user->name = $userData['name'];
        $user->email = $userData['email'];

        // Create Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        $cookie = Cookie::make(
            'jwt',
            $token,
            60 * 24 * 7, // 7 days
            '/',
            null,
            config('session.secure'),
            true // HttpOnly
        );

        return response()->json([
            'message' => 'Login successful',
            'user' => $userData
        ], 200)->withCookie($cookie);
    }

    public function verify(Request $request, $id, $hash)
    {
        try {
            // 1. Fetch the user
            $userResponse = Http::get(env('USER_MICROSERVICE_URL') . '/api/users/' . $id);

            // 2. Check for non-200 responses
            if (!$userResponse->successful()) {
                Log::error('Failed to fetch user from user-microservice', [
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body()
                ]);
                return response()->json(['message' => 'Could not retrieve user information.'], 502); // 502 Bad Gateway is appropriate
            }

            $user = $userResponse->json('data'); // Use the 'data' key, default to null if not found

            // 3. Check if user data is valid
            if (!$user || !isset($user['email'])) {
                Log::error('User data from user-microservice is invalid.', ['user_data' => $user]);
                return response()->json(['message' => 'Invalid user data received.'], 502);
            }

            // 4. Check hash
            if (!hash_equals((string) $hash, sha1($user['email']))) {
                return response()->json(['message' => 'Invalid verification link.'], 400);
            }

            // 5. Check if already verified
            if ($user['email_verified_at']) {
                return response()->json(['message' => 'Email already verified.'], 400);
            }

            // 6. Mark as verified
            $patchResponse = Http::patch(env('USER_MICROSERVICE_URL') . '/api/users/' . $id, [
                'email_verified_at' => Carbon::now(),
            ]);

            if (!$patchResponse->successful()) {
                Log::error('Failed to update user in user-microservice', [
                    'status' => $patchResponse->status(),
                    'body' => $patchResponse->body()
                ]);
                return response()->json(['message' => 'Failed to finalize verification.'], 502);
            }

            return response()->json(['message' => 'Email successfully verified.']);
        } catch (\Exception $e) {
            // Catch any other unexpected errors
            Log::error('An unexpected error occurred in email verification.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An internal server error occurred.'], 500);
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        // Check if password_reset_tokens table exists
        if (!DB::getSchemaBuilder()->hasTable('password_reset_tokens')) {
            Log::error('password_reset_tokens table does not exist.');
            return response()->json(['message' => 'Password reset functionality is not configured.'], 500);
        }

        // Fetch user from user-microservice
        $userResponse = Http::get(env('USER_MICROSERVICE_URL') . '/api/users/email/' . $request->email);

        if (!$userResponse->successful()) {
            // For security, always return a generic success message even if user not found
            return response()->json(['message' => 'If your email address exists in our database, you will receive a password recovery link at your email address.'], 200);
        }

        $user = $userResponse->json()['data'];

        // Generate token
        $token = Str::random(60);

        // Store token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user['email']],
            ['token' => \Illuminate\Support\Facades\Hash::make($token), 'created_at' => Carbon::now()]
        );

        // Generate reset link (frontend URL)
        $resetUrl = env('APP_URL') . '/reset-password?token=' . $token . '&email=' . $user['email']; // Assuming frontend handles this route

        // Dispatch email job
        $emailData = [
            'to' => $user['email'],
            'subject' => 'Password Reset Request',
            'body' => 'You are receiving this email because we received a password reset request for your account.\n\n' .
                'Please click the following link to reset your password: ' . $resetUrl . '\n\n' .
                'If you did not request a password reset, no further action is required.'
        ];
        SendEmailJob::dispatch($emailData)->onQueue('send_email');

        return response()->json(['message' => 'If your email address exists in our database, you will receive a password recovery link at your email address.'], 200);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        // Check if password_reset_tokens table exists
        if (!DB::getSchemaBuilder()->hasTable('password_reset_tokens')) {
            Log::error('password_reset_tokens table does not exist.');
            return response()->json(['message' => 'Password reset functionality is not configured.'], 500);
        }

        // Find the token
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$tokenRecord || !\Illuminate\Support\Facades\Hash::check($request->token, $tokenRecord->token)) {
            return response()->json(['message' => 'This password reset token is invalid.'], 400);
        }

        // Check if token has expired (e.g., 60 minutes)
        $createdAt = Carbon::parse($tokenRecord->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'This password reset token has expired.'], 400);
        }

        // Fetch user from user-microservice by email
        $userResponse = Http::get(env('USER_MICROSERVICE_URL') . '/api/users/email/' . $request->email);

        if (!$userResponse->successful()) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user = $userResponse->json()['data'];

        // Update password in user-microservice
        $patchResponse = Http::patch(env('USER_MICROSERVICE_URL') . '/api/users/' . $user['id'], [
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        if (!$patchResponse->successful()) {
            Log::error('Failed to update password in user-microservice', [
                'status' => $patchResponse->status(),
                'body' => $patchResponse->body()
            ]);
            return response()->json(['message' => 'Failed to reset password.'], 500);
        }

        // Delete the token after successful reset
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully.'], 200);
    }

    public function logout(Request $request)
    {
        // This part works correctly. The token is deleted from the database.
        $request->user()->currentAccessToken()->delete();

        // The cookie clearing fails due to an environment issue.
        // We will return a success response and let the frontend handle cookie deletion.
        return response()->json(['message' => 'Logout successful on server. Please clear the authentication cookie on the client-side.'], 200);
    }

}

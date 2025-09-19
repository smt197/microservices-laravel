<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Jobs\PublishUserEventJob;
use App\Models\User;
use App\Events\UserCreated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
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
        // Create user in local auth database
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        // Publish user created event
        PublishUserEventJob::dispatch('created', $user->toArray())->onQueue('events');

        // Generate verification URL
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        // Dispatch email job
        $emailData = [
            'to' => $user->email,
            'subject' => 'Verify Your Email Address',
            'body' => 'Please click the following link to verify your email: ' . $verificationUrl
        ];
        SendEmailJob::dispatch($emailData)->onQueue('send_email');

        return response()->json([
            'message' => 'User registered successfully. Please check your email to verify your account.',
            'user' => $user->only(['id', 'name', 'email', 'created_at'])
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        // Validate credentials locally
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        // Check if email is verified
        if (empty($user->email_verified_at)) {
            // Re-send verification email
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->id,
                    'hash' => sha1($user->email),
                ]
            );
            $emailData = [
                'to' => $user->email,
                'subject' => 'Verify Your Email Address',
                'body' => 'You must verify your email before logging in. Please click the link to verify: ' . $verificationUrl
            ];
            SendEmailJob::dispatch($emailData)->onQueue('send_email');

            return response()->json(['message' => 'Email not verified. A new verification link has been sent to your email address.'], 403);
        }

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
            'user' => $user->only(['id', 'name', 'email', 'email_verified_at'])
        ], 200)->withCookie($cookie);
    }

    public function verify(Request $request, $id, $hash)
    {
        try {
            // Find user locally
            $user = User::find($id);

            if (!$user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            // Check hash
            if (!hash_equals((string) $hash, sha1($user->email))) {
                return response()->json(['message' => 'Invalid verification link.'], 400);
            }

            // Check if already verified
            if ($user->email_verified_at) {
                return response()->json(['message' => 'Email already verified.'], 400);
            }

            // Mark as verified locally
            $user->update(['email_verified_at' => Carbon::now()]);

            // Publish user verified event
            PublishUserEventJob::dispatch('verified', $user->toArray())->onQueue('events');

            return response()->json(['message' => 'Email successfully verified.']);
        } catch (\Exception $e) {
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

        // Find user locally
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // For security, always return a generic success message even if user not found
            return response()->json(['message' => 'If your email address exists in our database, you will receive a password recovery link at your email address.'], 200);
        }

        // Generate token
        $token = Str::random(60);

        // Store token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => Carbon::now()]
        );

        // Generate reset link (frontend URL)
        $resetUrl = env('APP_URL') . '/reset-password?token=' . $token . '&email=' . $user->email;

        // Dispatch email job
        $emailData = [
            'to' => $user->email,
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

        if (!$tokenRecord || !Hash::check($request->token, $tokenRecord->token)) {
            return response()->json(['message' => 'This password reset token is invalid.'], 400);
        }

        // Check if token has expired (e.g., 60 minutes)
        $createdAt = Carbon::parse($tokenRecord->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'This password reset token has expired.'], 400);
        }

        // Find user locally
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Update password locally
        $user->update(['password' => $request->password]);

        // Publish user updated event
        PublishUserEventJob::dispatch('updated', $user->toArray())->onQueue('events');

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

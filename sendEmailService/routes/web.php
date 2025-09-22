<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-email', function () {
    try {
        Mail::raw('Test email from sendEmailService', function ($message) {
            $message->to('test@example.com')
                ->subject('Test Email');
        });

        return response()->json([
            'message' => 'Email sent successfully!',
            'config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to send email: ' . $e->getMessage(),
            'config' => [
                'mailer' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
                'port' => config('mail.mailers.smtp.port'),
                'from' => config('mail.from.address'),
            ]
        ], 500);
    }
});

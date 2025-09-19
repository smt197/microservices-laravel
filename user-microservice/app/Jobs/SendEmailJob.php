<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The email address to send to.
     */
    public string $email;

    /**
     * The email subject.
     */
    public string $subject;

    /**
     * The email message.
     */
    public string $message;

    /**
     * Create a new job instance.
     */
    public function __construct(string $email, string $subject, string $message)
    {
        $this->email = $email;
        $this->subject = $subject;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Log the email sending attempt
            Log::info('Sending email', [
                'email' => $this->email,
                'subject' => $this->subject,
                'message' => $this->message
            ]);

            // In a real application, you would use Mail::send() or a mail service
            // For now, we'll just log the action
            Log::info('Email sent successfully to: ' . $this->email);

            // Example of how you would send an actual email:
            
            Mail::raw($this->message, function ($mail) {
                $mail->to($this->email)
                     ->subject($this->subject);
            });
            

        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'email' => $this->email,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendEmailJob failed', [
            'email' => $this->email,
            'subject' => $this->subject,
            'exception' => $exception->getMessage()
        ]);
    }
}

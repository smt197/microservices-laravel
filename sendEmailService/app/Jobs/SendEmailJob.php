<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * The email data.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = $this->data;

        \Log::info('SendEmailJob started', ['data' => $data]);

        try {
            Mail::raw($data['body'], function ($message) use ($data) {
                $message->to($data['to'])
                    ->subject($data['subject']);
            });

            \Log::info('Email sent successfully', ['to' => $data['to']]);
        } catch (\Exception $e) {
            \Log::error('Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }
}

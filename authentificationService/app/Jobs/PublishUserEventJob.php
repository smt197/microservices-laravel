<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class PublishUserEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $eventType;
    public $userData;

    public function __construct(string $eventType, array $userData)
    {
        $this->eventType = $eventType;
        $this->userData = $userData;
    }

    public function handle()
    {
        try {
            $connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'rabbitmq_server'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'admin'),
                env('RABBITMQ_PASSWORD', 'password'),
                env('RABBITMQ_VHOST', '/')
            );

            $channel = $connection->channel();

            // Declare exchange for user events
            $channel->exchange_declare('user_events', 'topic', false, true, false);

            $eventData = [
                'event_type' => $this->eventType,
                'data' => $this->userData,
                'timestamp' => now()->toISOString(),
                'service' => 'authentificationService'
            ];

            $message = new AMQPMessage(
                json_encode($eventData),
                ['content_type' => 'application/json', 'delivery_mode' => 2]
            );

            $routingKey = "user.{$this->eventType}";
            $channel->basic_publish($message, 'user_events', $routingKey);

            Log::info("User event published successfully", [
                'event_type' => $this->eventType,
                'routing_key' => $routingKey,
                'user_id' => $this->userData['id'] ?? null
            ]);

            $channel->close();
            $connection->close();

        } catch (\Exception $e) {
            Log::error('Failed to publish user event', [
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
                'user_data' => $this->userData
            ]);
            throw $e;
        }
    }
}
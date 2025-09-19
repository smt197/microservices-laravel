<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUserEventJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class ConsumeUserEventsCommand extends Command
{
    protected $signature = 'rabbitmq:consume-user-events';
    protected $description = 'Consume user events from RabbitMQ';

    public function handle()
    {
        $this->info('Starting user events consumer...');

        try {
            $connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'rabbitmq_server'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'admin'),
                env('RABBITMQ_PASSWORD', 'password'),
                env('RABBITMQ_VHOST', '/')
            );

            $channel = $connection->channel();

            // Declare exchange
            $channel->exchange_declare('user_events', 'topic', false, true, false);

            // Declare queue for this service
            $queueName = 'user_microservice.user_events';
            $channel->queue_declare($queueName, false, true, false, false);

            // Bind queue to exchange with routing keys
            $channel->queue_bind($queueName, 'user_events', 'user.created');
            $channel->queue_bind($queueName, 'user_events', 'user.updated');
            $channel->queue_bind($queueName, 'user_events', 'user.verified');

            $callback = function ($msg) {
                try {
                    $eventData = json_decode($msg->body, true);

                    Log::info('Received user event', [
                        'routing_key' => $msg->getRoutingKey(),
                        'event_type' => $eventData['event_type'] ?? 'unknown'
                    ]);

                    // Dispatch to Laravel queue for processing
                    ProcessUserEventJob::dispatch($eventData);

                    // Acknowledge message
                    $msg->ack();

                } catch (\Exception $e) {
                    Log::error('Failed to process user event message', [
                        'error' => $e->getMessage(),
                        'message_body' => $msg->body
                    ]);

                    // Reject and requeue message
                    $msg->nack(false, true);
                }
            };

            $channel->basic_qos(null, 1, null);
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            $this->info("Waiting for user events. To exit press CTRL+C");

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();

        } catch (\Exception $e) {
            $this->error('Failed to consume user events: ' . $e->getMessage());
            Log::error('RabbitMQ consumer error', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }
}
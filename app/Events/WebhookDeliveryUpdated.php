<?php

namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after every forwarding attempt (success or failure) so the dashboard
 * delivery badge updates live, including when a chain exhausts retries (DLQ).
 */
class WebhookDeliveryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Delivery $delivery,
        public bool $exhausted = false,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('endpoint.'.$this->delivery->webhookEvent->endpoint->token),
        ];
    }

    public function broadcastAs(): string
    {
        return 'delivery.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->delivery->webhook_event_id,
            'attempt' => $this->delivery->attempt,
            'status_code' => $this->delivery->status_code,
            'delivered' => $this->delivery->delivered,
            'exhausted' => $this->exhausted,
            'error' => $this->delivery->error,
        ];
    }
}

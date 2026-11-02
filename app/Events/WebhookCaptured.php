<?php

namespace App\Events;

use App\Models\WebhookEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired from ProcessCapturedWebhook after the event row is persisted.
 * Broadcast over a private per-endpoint channel; the dashboard live-updates on it.
 *
 * The broadcast payload is deliberately compact (no body): clients fetch the
 * full event via HTTP when the row is expanded.
 */
class WebhookCaptured implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WebhookEvent $webhookEvent,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('endpoint.'.$this->webhookEvent->endpoint->token),
        ];
    }

    public function broadcastAs(): string
    {
        return 'captured';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $event = $this->webhookEvent;

        return [
            'id' => $event->id,
            'method' => $event->method,
            'path' => $event->path,
            'content_type' => $event->content_type,
            'payload_size' => $event->payload_size,
            'received_at' => $event->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Jobs;

use App\Events\WebhookCaptured;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a persisted capture to its endpoint's private channel. The row
 * already exists (ingest persists before dispatch); forwarding runs in the
 * next chained job, so it can never race ahead of the visible event.
 */
class ProcessCapturedWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public WebhookEvent $webhookEvent,
    ) {}

    public function handle(): void
    {
        WebhookCaptured::dispatch($this->webhookEvent);
    }
}

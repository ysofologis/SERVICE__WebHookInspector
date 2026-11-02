<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class PruneWebhookEvents extends Command
{
    protected $signature = 'webhooks:prune {--days= : Override retention in days}';

    protected $description = 'Delete captured webhook events older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('webhook-inspector.retention_days', 7));

        $deleted = WebhookEvent::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} webhook events older than {$days} days.");

        return self::SUCCESS;
    }
}

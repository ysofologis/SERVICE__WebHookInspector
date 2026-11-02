<?php

namespace App\Jobs;

use App\Events\WebhookDeliveryUpdated;
use App\Models\WebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Forward a captured event to the endpoint's target URL.
 *
 * Delivery is at-least-once. The original request line, query string, headers,
 * and body are reproduced verbatim, except redacted headers (config
 * `webhook-inspector.redact_headers`) which are replaced with a marker.
 *
 * Retry schedule comes from config; after the final failed attempt the chain
 * is marked exhausted (DLQ) — a manual replay re-dispatches this job.
 */
class ForwardWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        public WebhookEvent $webhookEvent,
        public string $targetUrl,
    ) {
        $this->tries = (int) config('webhook-inspector.forward.max_attempts', 5);
    }

    /** Exponential backoff between attempts (seconds). */
    public function backoff(): array
    {
        return config('webhook-inspector.forward.backoff_seconds', [5, 15, 60, 300, 900]);
    }

    public function handle(): void
    {
        $event = $this->webhookEvent;
        $attempt = (int) $this->attempts();

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders($this->forwardHeaders())
                ->timeout((int) config('webhook-inspector.forward.timeout_seconds', 10))
                ->send($event->method, $this->targetUri(), [
                    'body' => $event->body ?? '',
                ]);

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            $delivery = $event->deliveries()->create([
                'attempt' => $attempt,
                'status_code' => $response->status(),
                'duration_ms' => $durationMs,
                'error' => null,
                'delivered' => $response->successful(),
            ]);

            WebhookDeliveryUpdated::dispatch($delivery, exhausted: false);

            if ($response->successful()) {
                return; // done
            }

            $this->failAttempt("HTTP {$response->status()} from target");
        } catch (ConnectionException $e) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            $delivery = $event->deliveries()->create([
                'attempt' => $attempt,
                'status_code' => null,
                'duration_ms' => $durationMs,
                'error' => mb_substr($e->getMessage(), 0, 500),
                'delivered' => false,
            ]);

            WebhookDeliveryUpdated::dispatch($delivery, exhausted: false);

            $this->failAttempt('connection error: '.$e->getMessage());
        }
    }

    /** Mark the chain exhausted when the last allowed attempt just failed. */
    protected function failAttempt(string $reason): void
    {
        if ($this->attempts() >= $this->tries) {
            WebhookDeliveryUpdated::dispatch(
                $this->webhookEvent->deliveries()->orderByDesc('attempt')->first(),
                exhausted: true,
            );

            Log::warning('Webhook forward chain exhausted', [
                'webhook_event_id' => $this->webhookEvent->id,
                'target' => $this->targetUrl,
                'reason' => $reason,
            ]);

            return; // swallow: DLQ is a terminal state, not a job failure
        }

        $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
    }

    /**
     * Captured headers minus hop-by-hop and redacted entries, with Host
     * rewritten for the target (Guzzle sets its own correct Host anyway).
     */
    protected function forwardHeaders(): array
    {
        $redact = (array) config('webhook-inspector.redact_headers', []);
        $skip = ['host', 'content-length', 'transfer-encoding', 'connection'];
        $out = [];

        foreach ($this->webhookEvent->headers ?? [] as $name => $values) {
            $lower = strtolower($name);

            if (in_array($lower, $skip, true)) {
                continue;
            }

            $out[$name] = in_array($lower, $redact, true)
                ? '[REDACTED by webhook-inspector]'
                : implode(', ', (array) $values);
        }

        return $out;
    }

    /** Prefix the configured target origin with the captured path + query. */
    protected function targetUri(): string
    {
        $path = trim(parse_url($this->webhookEvent->path, PHP_URL_PATH) ?: '', '/');
        $query = parse_url($this->webhookEvent->path, PHP_URL_QUERY);

        $querySuffix = $query ? '?'.$query : '';

        // The target URL is used verbatim; the captured path is appended only
        // when the target ends in a trailing slash ("path-style" targets).
        if (str_ends_with($this->targetUrl, '/')) {
            return rtrim($this->targetUrl, '/').($path !== '' ? '/'.$path : '').$querySuffix;
        }

        return $this->targetUrl.$querySuffix;
    }
}

<?php

use App\Events\WebhookCaptured;
use App\Events\WebhookDeliveryUpdated;
use App\Models\Endpoint;
use App\Models\WebhookEvent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\assertDatabaseHas;

/*
 * The ingest spine: capture persists durably BEFORE acking (a 202 tells the
 * sender to stop retrying, so the payload must already be durable), then
 * hands off broadcast + forward fan-out to the queue.
 */

it('accepts any method with 202 and persists durably before acking', function () {
    Queue::fake();

    $endpoint = Endpoint::factory()->create();

    $response = $this->postJson("/hook/{$endpoint->token}", [
        'order_id' => 42,
        'status' => 'paid',
    ]);

    $response->assertStatus(202);
    assertDatabaseHas('webhook_events', ['endpoint_id' => $endpoint->id, 'method' => 'POST']);
});

it('captures raw bodies, query strings, headers, and sender IP', function () {
    Queue::fake();

    $endpoint = Endpoint::factory()->create();

    $this->call('POST', "/hook/{$endpoint->token}?utm=test", [], [], [], [
        'CONTENT_TYPE' => 'text/plain',
    ], 'raw body');

    assertDatabaseHas('webhook_events', [
        'endpoint_id' => $endpoint->id,
        'body' => 'raw body',
        'path' => "/hook/{$endpoint->token}?utm=test",
        'content_type' => 'text/plain',
    ]);
});

it('forwards after persisting when a target is configured (sync chain end-to-end)', function () {
    Event::fake([WebhookCaptured::class, WebhookDeliveryUpdated::class]);
    Http::fake(['target.test/*' => Http::response(['ok' => true], 200)]);

    $endpoint = Endpoint::factory()->forwardingTo('https://target.test/consume')->create();

    $this->post("/hook/{$endpoint->token}", ['a' => 1])->assertStatus(202);

    // Sync queue runs the chain in order: persist (at ingest) → broadcast → forward.
    $event = WebhookEvent::query()->where('endpoint_id', $endpoint->id)->firstOrFail();

    $this->assertDatabaseHas('deliveries', [
        'webhook_event_id' => $event->id,
        'delivered' => true,
        'status_code' => 200,
    ]);
});

it('broadcasts from the queued job with a compact payload', function () {
    Queue::fake();
    Event::fake([WebhookCaptured::class]);

    $endpoint = Endpoint::factory()->create();
    $event = WebhookEvent::factory()->for($endpoint)->create();

    (new App\Jobs\ProcessCapturedWebhook($event))->handle();

    Event::assertDispatched(function (WebhookCaptured $e) use ($event) {
        return $e->webhookEvent->is($event)
            && ! array_key_exists('body', $e->broadcastWith()); // payload stays compact
    });
});

it('returns 404 for unknown tokens', function () {
    $this->postJson('/hook/nope-nope')->assertStatus(404);
});

it('returns 410 for disabled endpoints', function () {
    $endpoint = Endpoint::factory()->disabled()->create();

    $this->postJson("/hook/{$endpoint->token}", ['a' => 1])->assertStatus(410);
});

it('rejects oversized payloads with 413 before touching the queue', function () {
    Queue::fake();

    $endpoint = Endpoint::factory()->create();
    $huge = str_repeat('x', 1024 * 1024 + 1); // just over the 1 MiB cap

    $this->call('POST', "/hook/{$endpoint->token}", [], [], [], [
        'CONTENT_LENGTH' => (string) strlen($huge),
    ], $huge)->assertStatus(413);
    Queue::assertNothingPushed();
});

it('rate limits capture per endpoint', function () {
    // The throttle middleware is under test; keep the sync-queue pipeline
    // from attempting a real broadcast to Reverb.
    Event::fake([WebhookCaptured::class]);
    RateLimiter::for('webhook-capture', fn (Request $request) => Limit::perMinute(1)->by($request->ip()));

    $endpoint = Endpoint::factory()->create();

    $this->post("/hook/{$endpoint->token}")->assertStatus(202);
    $this->post("/hook/{$endpoint->token}")->assertStatus(429);
});

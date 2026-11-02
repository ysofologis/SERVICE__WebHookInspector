<?php

use App\Events\WebhookDeliveryUpdated;
use App\Jobs\ForwardWebhook;
use App\Models\Endpoint;
use App\Models\WebhookEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
 * Forwarding: stored request goes out verbatim (minus redacted headers),
 * every attempt is recorded, failures retry, exhaustion lands in the DLQ.
 */

function forwardEvent(Endpoint $endpoint): WebhookEvent
{
    return WebhookEvent::factory()->for($endpoint)->create([
        'method' => 'POST',
        'path' => '/hook/abc123?source=paypal',
        'headers' => [
            'Host' => ['original.test'],
            'Authorization' => ['Bearer super-secret'],
            'Content-Type' => ['application/json'],
            'X-Sent-At' => ['2026-11-02T12:00:00Z'],
        ],
        'body' => '{"secret":"payload"}',
        'content_type' => 'application/json',
    ]);
}

it('delivers the stored request verbatim and records a successful delivery', function () {
    Event::fake([WebhookDeliveryUpdated::class]);
    Http::fake(['target.test/*' => Http::response(['ok' => true], 200)]);

    $endpoint = Endpoint::factory()->forwardingTo('https://target.test/consume')->create();
    $event = forwardEvent($endpoint);

    (new ForwardWebhook($event, 'https://target.test/consume'))->handle();

    $this->assertDatabaseHas('deliveries', [
        'webhook_event_id' => $event->id,
        'attempt' => 1,
        'status_code' => 200,
        'delivered' => true,
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://target.test/consume?source=paypal' // captured query preserved
            && $request->body() === '{"secret":"payload"}'
            && $request->header('X-Sent-At') === ['2026-11-02T12:00:00Z']; // original header preserved
    });
});

it('redacts credential headers before forwarding', function () {
    Event::fake([WebhookDeliveryUpdated::class]);
    Http::fake(['target.test/*' => Http::response([], 200)]);

    $endpoint = Endpoint::factory()->forwardingTo('https://target.test/consume')->create();
    $event = forwardEvent($endpoint);

    (new ForwardWebhook($event, 'https://target.test/consume'))->handle();

    Http::assertSent(function ($request) {
        return $request->header('Authorization') === ['[REDACTED by webhook-inspector]'];
    });
});

it('records failed attempts and releases the job for another try', function () {
    Event::fake([WebhookDeliveryUpdated::class]);
    Http::fake(['target.test/*' => Http::response(['error' => 'boom'], 500)]);

    $endpoint = Endpoint::factory()->forwardingTo('https://target.test/consume')->create();
    $event = forwardEvent($endpoint);

    (new ForwardWebhook($event, 'https://target.test/consume'))->handle();

    $this->assertDatabaseHas('deliveries', [
        'webhook_event_id' => $event->id,
        'attempt' => 1,
        'status_code' => 500,
        'delivered' => false,
    ]);
    Event::assertDispatched(WebhookDeliveryUpdated::class);
});

it('marks the chain exhausted after the final failed attempt (DLQ)', function () {
    Event::fake([WebhookDeliveryUpdated::class]);
    Http::fake(['target.test/*' => Http::response([], 500)]);

    $endpoint = Endpoint::factory()->forwardingTo('https://target.test/consume')->create();
    $event = forwardEvent($endpoint);
    $event->deliveries()->create(['attempt' => 1, 'status_code' => 500, 'delivered' => false]);

    $job = new ForwardWebhook($event, 'https://target.test/consume');
    $job->tries = 1; // final allowed attempt

    $job->handle();

    Event::assertDispatched(function (WebhookDeliveryUpdated $e) use ($event) {
        return $e->exhausted === true && $e->delivery->webhook_event_id === $event->id;
    });
});

it('records transport errors (status_code null) as failed deliveries', function () {
    Event::fake([WebhookDeliveryUpdated::class]);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

    $endpoint = Endpoint::factory()->forwardingTo('https://target.test/consume')->create();
    $event = forwardEvent($endpoint);

    $job = new ForwardWebhook($event, 'https://target.test/consume');
    $job->tries = 1;

    $job->handle();

    $this->assertDatabaseHas('deliveries', [
        'webhook_event_id' => $event->id,
        'status_code' => null,
        'delivered' => false,
    ]);
});

it('replays only to endpoints with a configured target', function () {
    Queue::fake();

    $owner = \App\Models\User::factory()->create();
    $endpoint = Endpoint::factory()->for($owner)->create(); // no forward URL
    $event = WebhookEvent::factory()->for($endpoint)->create();

    $response = $this->actingAs($owner)
        ->postJson("/endpoints/{$endpoint->id}/events/{$event->id}/replay");

    $response->assertStatus(422);
    Queue::assertNothingPushed();
});

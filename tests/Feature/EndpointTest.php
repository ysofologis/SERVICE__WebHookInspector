<?php

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;

/*
 * Dashboard access control and endpoint lifecycle.
 */

it('requires login for the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('creates an endpoint with a generated token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/endpoints', [
        'name' => 'stripe-sandbox',
        'forward_to_url' => 'https://myapp.test/consume',
    ]);

    $endpoint = $user->endpoints()->firstOrFail();
    $response->assertRedirect("/endpoints/{$endpoint->id}");
    assertDatabaseHas('endpoints', [
        'user_id' => $user->id,
        'name' => 'stripe-sandbox',
        'enabled' => true,
    ]);
    expect($endpoint->token)->toBeString()->toHaveLength(36);
});

it('forbids other users from viewing an endpoint', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $endpoint = Endpoint::factory()->for($owner)->create();
    $event = \App\Models\WebhookEvent::factory()->for($endpoint)->create();

    $this->actingAs($stranger)->get("/endpoints/{$endpoint->id}")->assertForbidden();
    $this->actingAs($stranger)
        ->postJson("/endpoints/{$endpoint->id}/events/{$event->id}/replay")
        ->assertForbidden();
});

it('authorizes only the owner on the private broadcast channel', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $endpoint = Endpoint::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-endpoint.{$endpoint->token}",
            'socket_id' => '1234.5678',
        ])
        ->assertOk();

    $this->actingAs($stranger)
        ->postJson('/broadcasting/auth', [
            'channel_name' => "private-endpoint.{$endpoint->token}",
            'socket_id' => '1234.5678',
        ])
        ->assertForbidden();
});

it('allows the owner to replay an event to the configured target', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $endpoint = Endpoint::factory()->for($owner)->forwardingTo('https://target.test/consume')->create();
    $event = \App\Models\WebhookEvent::factory()->for($endpoint)->create();

    $this->actingAs($owner)
        ->postJson("/endpoints/{$endpoint->id}/events/{$event->id}/replay")
        ->assertOk()
        ->assertJson(['replayed' => true]);

    Queue::assertPushed(\App\Jobs\ForwardWebhook::class, fn ($job) => $job->webhookEvent->is($event));
});

it('deletes an endpoint and cascades its events', function () {
    $owner = User::factory()->create();
    $endpoint = Endpoint::factory()->for($owner)->create();
    $event = \App\Models\WebhookEvent::factory()->for($endpoint)->create();

    $this->actingAs($owner)->delete("/endpoints/{$endpoint->id}");

    $this->assertDatabaseMissing('endpoints', ['id' => $endpoint->id]);
    $this->assertDatabaseMissing('webhook_events', ['id' => $event->id]);
});

it('prunes events older than the retention window', function () {
    $endpoint = Endpoint::factory()->create();
    $old = \App\Models\WebhookEvent::factory()->for($endpoint)->create();
    $old->forceFill(['created_at' => now()->subDays(8)])->save();
    $fresh = \App\Models\WebhookEvent::factory()->for($endpoint)->create();

    $this->artisan('webhooks:prune')->expectsOutputToContain('Pruned 1');

    $this->assertDatabaseMissing('webhook_events', ['id' => $old->id]);
    $this->assertDatabaseHas('webhook_events', ['id' => $fresh->id]);
});

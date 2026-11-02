<?php

namespace Database\Factories;

use App\Models\Endpoint;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        $body = json_encode(['id' => fake()->randomNumber(), 'status' => 'paid']);

        return [
            'endpoint_id' => Endpoint::factory(),
            'method' => 'POST',
            'path' => '/hook',
            'query' => null,
            'headers' => [
                'Host' => ['example.test'],
                'Content-Type' => ['application/json'],
                'User-Agent' => ['Stripe/1.0 (+https://stripe.com/docs/webhooks)'],
            ],
            'body' => $body,
            'content_type' => 'application/json',
            'payload_size' => strlen($body),
            'ip' => fake()->ipv4(),
        ];
    }

    /** Form-encoded body (e.g. from classic payment gateways). */
    public function formEncoded(): static
    {
        return $this->state(fn () => [
            'headers' => ['Content-Type' => ['application/x-www-form-urlencoded']],
            'body' => 'order_id=42&status=paid&amount=19.99',
            'content_type' => 'application/x-www-form-urlencoded',
        ]);
    }
}

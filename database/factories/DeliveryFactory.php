<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        return [
            'webhook_event_id' => WebhookEvent::factory(),
            'attempt' => 1,
            'status_code' => 200,
            'duration_ms' => fake()->numberBetween(40, 800),
            'error' => null,
            'delivered' => true,
        ];
    }

    /** A failed attempt that exhausted retries → lands in the DLQ. */
    public function exhausted(): static
    {
        return $this->state(fn () => [
            'attempt' => 5,
            'status_code' => 500,
            'error' => 'HTTP 500 from target',
            'delivered' => false,
        ]);
    }
}

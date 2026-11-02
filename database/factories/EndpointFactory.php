<?php

namespace Database\Factories;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Endpoint>
 */
class EndpointFactory extends Factory
{
    protected $model = Endpoint::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => (string) Str::uuid7(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'forward_to_url' => null,
            'enabled' => true,
        ];
    }

    /** Endpoint configured to forward to a target URL. */
    public function forwardingTo(?string $url = 'https://example.test/hook'): static
    {
        return $this->state(fn () => ['forward_to_url' => $url]);
    }

    /** Disabled endpoints reject captures with 410. */
    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}

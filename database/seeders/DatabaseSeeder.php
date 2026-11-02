<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data so the dashboard is populated on first login.
     * Credentials: demo@webhook.test / password
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo',
            'email' => 'demo@webhook.test',
        ]);

        $stripe = Endpoint::factory()->for($user)->create([
            'name' => 'stripe-sandbox',
            'description' => 'Stripe test-mode webhook receiver',
        ]);

        $gateway = Endpoint::factory()->for($user)->forwardingTo('https://billing.internal/consume')->create([
            'name' => 'legacy-gateway-bridge',
            'description' => 'Old payment gateway → new billing service',
        ]);

        WebhookEvent::factory()->count(3)->for($stripe)->create();

        $forwarded = WebhookEvent::factory()->for($gateway)->create([
            'path' => '/hook/bridge?txn=88123',
        ]);
        Delivery::factory()->for($forwarded, 'webhookEvent')->create();

        $dead = WebhookEvent::factory()->for($gateway)->create([
            'path' => '/hook/bridge?txn=88124',
        ]);
        Delivery::factory()->exhausted()->for($dead, 'webhookEvent')->create();
    }
}

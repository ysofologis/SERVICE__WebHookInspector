<?php

namespace App\Policies;

use App\Models\Endpoint;
use App\Models\User;

class EndpointPolicy
{
    public function view(User $user, Endpoint $endpoint): bool
    {
        return $user->id === $endpoint->user_id;
    }

    public function update(User $user, Endpoint $endpoint): bool
    {
        return $user->id === $endpoint->user_id;
    }

    public function delete(User $user, Endpoint $endpoint): bool
    {
        return $user->id === $endpoint->user_id;
    }
}

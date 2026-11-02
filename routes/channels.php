<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private per-endpoint channel: only the owner may listen for live captures.
Broadcast::channel('endpoint.{token}', function ($user, string $token) {
    return $user->endpoints()->where('token', $token)->exists();
});

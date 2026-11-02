<?php

namespace App\Support;

use App\Models\WebhookEvent;

/**
 * Decides where (and whether) a captured event should be forwarded.
 *
 * Replay semantics: the endpoint's current forward_to_url is the source of
 * truth. A manual replay of an older event therefore goes to the URL the
 * endpoint points at *now* — that's the useful behavior (e.g. after fixing
 * the downstream consumer, replay the backlog at it).
 */
final class ForwardTargetResolver
{
    /**
     * The forward target for an event, or null when forwarding is off
     * (no configured URL, endpoint disabled, or event missing its endpoint).
     */
    public static function for(WebhookEvent $event): ?string
    {
        $endpoint = $event->endpoint;

        if ($endpoint === null || ! $endpoint->enabled) {
            return null;
        }

        $url = trim((string) $endpoint->forward_to_url);

        return $url !== '' ? $url : null;
    }
}

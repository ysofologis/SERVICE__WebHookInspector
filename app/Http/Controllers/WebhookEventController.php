<?php

namespace App\Http\Controllers;

use App\Jobs\ForwardWebhook;
use App\Models\Endpoint;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WebhookEventController extends Controller
{
    /** Full event detail (JSON) — fetched when a dashboard row is expanded. */
    public function show(Request $request, Endpoint $endpoint, WebhookEvent $event): JsonResponse
    {
        Gate::authorize('view', $endpoint);

        abort_unless($event->endpoint_id === $endpoint->id, 404);

        return response()->json([
            'id' => $event->id,
            'method' => $event->method,
            'path' => $event->path,
            'query' => $event->query,
            'headers' => $event->headers,
            'body' => $event->body,
            'content_type' => $event->content_type,
            'payload_size' => $event->payload_size,
            'ip' => $event->ip,
            'received_at' => $event->created_at?->toIso8601String(),
            'deliveries' => $event->deliveries()->orderByDesc('attempt')->get(),
        ]);
    }

    /**
     * Manual replay: re-dispatch the *stored* request verbatim (redactions
     * still apply) to the endpoint's current forward target.
     */
    public function replay(Request $request, Endpoint $endpoint, WebhookEvent $event): JsonResponse
    {
        Gate::authorize('update', $endpoint);

        abort_unless($event->endpoint_id === $endpoint->id, 404);

        $target = \App\Support\ForwardTargetResolver::for($event);

        if ($target === null) {
            return response()->json([
                'error' => 'No forward target configured for this endpoint.',
            ], 422);
        }

        ForwardWebhook::dispatch($event, $target);

        return response()->json(['replayed' => true]);
    }
}

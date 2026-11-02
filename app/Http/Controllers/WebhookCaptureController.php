<?php

namespace App\Http\Controllers;

use App\Jobs\ForwardWebhook;
use App\Jobs\ProcessCapturedWebhook;
use App\Models\Endpoint;
use App\Models\WebhookEvent;
use App\Support\ForwardTargetResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Symfony\Component\HttpFoundation\Response;

/**
 * The hot path. Contract: durably append the capture, ack 202 fast, then
 * let the queue do everything else.
 *
 * Why persist synchronously: a 202 tells the sender "captured, you can stop
 * retrying" — so the payload must already be durable. If capture only lived
 * in a queue payload, a lost job would silently drop a webhook the sender
 * will never send again. One indexed insert is cheap; that's the trade.
 *
 * The model is saved before dispatch so queue serialization round-trips a
 * real id (SerializesModels cannot rehydrate an unsaved model).
 */
class WebhookCaptureController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        $endpoint = Endpoint::query()
            ->where('token', $token)
            ->firstOrFail();

        if (! $endpoint->enabled) {
            return response()->json(['error' => 'endpoint disabled'], Response::HTTP_GONE);
        }

        $contentLength = (int) ($request->header('Content-Length', 0) ?: 0);

        if ($contentLength > config('webhook-inspector.max_body_bytes')) {
            return response()->json(['error' => 'payload too large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $event = new WebhookEvent([
            'method' => $request->getMethod(),
            'path' => $request->getRequestUri(),
            'query' => $request->query(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
            'content_type' => $request->header('Content-Type'),
            'payload_size' => max($contentLength, strlen($request->getContent())),
            'ip' => $request->ip(),
        ]);

        $event->endpoint()->associate($endpoint);
        $event->save();

        $jobs = [new ProcessCapturedWebhook($event)];

        if ($target = ForwardTargetResolver::for($event)) {
            $jobs[] = new ForwardWebhook($event, $target);
        }

        Bus::chain($jobs)->dispatch();

        return response()->json(['captured' => true], Response::HTTP_ACCEPTED);
    }
}

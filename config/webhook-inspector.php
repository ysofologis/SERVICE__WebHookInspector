<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Capture limits
    |--------------------------------------------------------------------------
    |
    | Ingest is the hot path of this app: it must stay cheap and bounded.
    | Bodies above max_body_bytes are rejected with 413 at the controller,
    | before any queue work is scheduled.
    |
    */

    'max_body_bytes' => env('WEBHOOK_MAX_BODY_BYTES', 1024 * 1024), // 1 MiB

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Events older than this many days are pruned by the `webhooks:prune`
    | scheduled command. Deliveries cascade with their event.
    |
    */

    'retention_days' => env('WEBHOOK_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Forwarding
    |--------------------------------------------------------------------------
    |
    | Forwarding is at-least-once: receivers must tolerate duplicates.
    | backoff_seconds[i] is the delay before retry attempt i+1.
    |
    */

    'forward' => [
        'max_attempts' => env('WEBHOOK_FORWARD_MAX_ATTEMPTS', 5),
        'backoff_seconds' => [5, 15, 60, 300, 900],
        'timeout_seconds' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Header redaction (forwarding)
    |--------------------------------------------------------------------------
    |
    | These headers are never forwarded verbatim to a replay/forward target.
    | A captured Authorization header replayed to a third-party host is a
    | credential leak waiting to happen.
    |
    */

    'redact_headers' => [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'proxy-authorization',
    ],

];

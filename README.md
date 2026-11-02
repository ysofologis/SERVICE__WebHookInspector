# webhook-inspector

**webhook-inspector** is a self-hosted webhook debugging tool. It gives every integration a private capture URL: point any sender — Stripe, GitHub, PayPal, a legacy gateway, plain `curl` — at it, and every request is recorded verbatim and appears live in a dashboard. From there you can inspect exactly what arrived (method, query string, headers, raw body), forward it to your real service, and replay any past request on demand.

### Purpose

Webhooks are push-based and fire-and-forget: if your receiver is down, misconfigured, or rejects a payload, most providers won't resend — the request is simply gone. And while building or debugging a consumer, the recurring question is *"what exactly did the provider send?"*, which today means log-diving or waiting for the next event. webhook-inspector sits between sender and receiver and makes that question answerable in seconds:

- **Capture before processing** — the payload is durably stored the moment it arrives; nothing is lost while your consumer is broken or half-built.
- **See the raw truth** — exact headers, query string, and body as the sender emitted them, not an interpretation.
- **Replay on demand** — fix your code, replay the same payload, iterate without waiting for the provider to send another event.
- **Observe delivery, don't guess it** — forwarding has production semantics: automatic retries with exponential backoff and a dead-letter queue you can inspect and re-drive.

## Example use cases

- **Debug a misbehaving provider webhook** — Stripe sends `invoice.payment_failed` with an unexpected shape; read the exact payload and headers in the dashboard instead of adding temporary logging to your receiver.
- **Develop a consumer without a live provider** — capture a few real payloads once, then replay them at `localhost` while you code; no test-mode webhook juggling or provider-dashboard clicking.
- **Bridge and audit legacy integrations** — an old gateway you can't change? Point it at the inspector, forward to the new service, and keep a 7-day record of everything received and every delivery attempt.
- **Verify retry/DLQ behavior safely** — set the forward target to a deliberately failing URL and watch the 5 s → 15 min backoff ladder and dead-letter transition before wiring the real consumer.
- **Harvest test fixtures** — captured bodies are realistic, provider-accurate fixtures for your consumer's test suite.

> Built as a portfolio piece demonstrating a production-shaped Laravel slice: **queues + job chaining, broadcasting (Reverb), authorization (policies + private channels), rate limiting, at-least-once delivery semantics, and a tested ingest path**.

## Architecture

```mermaid
sequenceDiagram
    participant S as Sender (Stripe / curl)
    participant C as POST /hook/{token}
    participant DB as SQLite
    participant Q as Queue (chain)
    participant R as Reverb (WebSocket)
    participant B as Browser (Echo + Alpine)

    S->>C: any method, any body
    C->>DB: INSERT webhook_events (durable before ack)
    C->>Q: Bus::chain([ProcessCapturedWebhook, ForwardWebhook?])
    C-->>S: 202 Accepted
    Q->>R: broadcast WebhookCaptured
    R-->>B: private-endpoint.{token} → live row
    Q->>S: forward to target (verbatim, redacted headers)
    Note over Q,S: non-2xx → retry w/ backoff (5 attempts)<br/>final failure → DLQ + manual replay
```

### Design decisions worth reading the code for

| Decision | Where | Why |
|---|---|---|
| Persist **before** acking 202 | `WebhookCaptureController` | A 202 tells the sender to stop retrying — the payload must already be durable. Queue-only capture loses webhooks silently if a job is lost. |
| Broadcast + forward via `Bus::chain` | controller → jobs | Forward can never race ahead of the persisted event, even with parallel workers. |
| Target URL used **verbatim** on forward (query string preserved) | `ForwardWebhook::targetUri()` | Surprise-path-appending breaks downstream routers; path-style targets opt in via trailing slash. |
| Credential headers redacted on forward | `config/webhook-inspector.php` | Replaying a captured `Authorization` header to a third-party host is a credential leak. |
| At-least-once delivery, documented | `ForwardWebhook` | Receivers must dedupe; the original headers (incl. `Idempotency-Key`) are forwarded unchanged. |
| Compact broadcast payloads | `WebhookCaptured::broadcastWith()` | WS fan-out carries the row id; clients fetch bodies on expand. |
| Private per-endpoint channels | `routes/channels.php` | Only the owner may listen; enforced server-side by token ownership. |

## Stack

Laravel 12 · Reverb (broadcasting) · database queue · SQLite · Blade + Alpine + Tailwind · Pest

## Local setup

```bash
composer install && npm install && npm run build
php artisan migrate
```

Run the four processes (dev):

```bash
php artisan serve                      # app
php artisan queue:work                 # broadcast + forwarding
php artisan reverb:start               # websockets
npm run dev                            # vite
```

Then: register → create an endpoint → fire:

```bash
curl -X POST 'http://localhost:8000/hook/<token>' \
  -H 'Content-Type: application/json' \
  -d '{"order_id":42,"status":"paid"}'
```

The event appears in the dashboard in realtime. Configure a forward target and failures will climb the retry ladder (5s → 15s → 60s → 5m → 15m) into the DLQ, where you can replay them.

## Operations notes

- Retention: `webhooks:prune` (scheduled daily 03:17) drops events older than `WEBHOOK_RETENTION_DAYS` (default 7).
- Body cap: `WEBHOOK_MAX_BODY_BYTES` (default 1 MiB) — enforced pre-queue with 413.
- Rate limit: 120 req/min per IP+endpoint (`webhook-capture` limiter) → 429.
- In production, tune the webserver's body-size limit to match the app cap, and run the queue worker + Reverb under a supervisor.

## Tests

```bash
php artisan test
```

46 tests / ~100 assertions covering: ingest durability + 202 contract, capture fidelity (raw bodies, query strings, headers), forwarding verbatim + redaction, retry → DLQ transitions, replay authorization, private-channel auth (owner vs stranger), rate limiting, size caps, retention pruning.

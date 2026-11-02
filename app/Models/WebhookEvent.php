<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEvent extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookEventFactory> */
    use HasFactory;

    protected $table = 'webhook_events';

    protected $fillable = [
        'method',
        'path',
        'query',
        'headers',
        'body',
        'content_type',
        'payload_size',
        'ip',
    ];

    protected $casts = [
        'query' => 'array',
        'headers' => 'array',
    ];

    /** @return BelongsTo<Endpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(Endpoint::class);
    }

    /** @return HasMany<Delivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderByDesc('attempt');
    }

    /** Latest delivery attempt, if any (null when not forwarded). */
    public function latestDelivery(): ?Delivery
    {
        return $this->deliveries->first();
    }

    /** Human-friendly payload preview: pretty JSON when decodable, else a size/type stub. */
    public function bodyPreview(int $maxBytes = 64 * 1024): string
    {
        if ($this->body === null || $this->body === '') {
            return '';
        }

        if (mb_strlen($this->body) > $maxBytes) {
            return sprintf('— too large to display (%s) —', \Illuminate\Support\Number::bytes($this->payload_size));
        }

        $decoded = json_decode($this->body);

        return json_last_error() === JSON_ERROR_NONE
            ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $this->body;
    }
}

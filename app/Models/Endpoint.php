<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Endpoint extends Model
{
    /** @use HasFactory<\Database\Factories\EndpointFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'forward_to_url',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $endpoint) {
            $endpoint->token ??= (string) Str::uuid7();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WebhookEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class)->latest();
    }

    /** Canonical public URL for this endpoint's capture route. */
    public function captureUrl(): string
    {
        return route('webhooks.capture', ['token' => $this->token]);
    }
}

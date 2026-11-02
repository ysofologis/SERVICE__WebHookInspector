<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    /** @use HasFactory<\Database\Factories\DeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'attempt',
        'status_code',
        'duration_ms',
        'error',
        'delivered',
    ];

    protected $casts = [
        'attempt' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'delivered' => 'boolean',
    ];

    /** @return BelongsTo<WebhookEvent, $this> */
    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }
}

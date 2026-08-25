<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    public const DEFAULT_EVENTS = [
        'order_created' => true,
        'order_ready' => true,
        'order_cancelled' => true,
        'order_paid' => true,
        'delivery_available' => true,
        'delivery_assigned' => true,
        'delivery_picked_up' => true,
        'delivery_completed' => true,
    ];

    protected $attributes = [
        'notifications_enabled' => true,
        'sound_enabled' => true,
        'volume' => 65,
        'quiet_hours_enabled' => false,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
    ];

    protected $fillable = [
        'user_id',
        'notifications_enabled',
        'sound_enabled',
        'volume',
        'quiet_hours_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'event_preferences',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'sound_enabled' => 'boolean',
        'volume' => 'integer',
        'quiet_hours_enabled' => 'boolean',
        'event_preferences' => 'array',
    ];

    public function eventPreferences(): array
    {
        return array_replace(self::DEFAULT_EVENTS, $this->event_preferences ?? []);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

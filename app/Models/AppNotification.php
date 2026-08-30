<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'announced_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function getIconAttribute(): string
    {
        return match ($this->event_key) {
            'order.created' => 'bx-receipt',
            'order.ready' => 'bx-check-circle',
            'order.cancelled' => 'bx-x-circle',
            'order.cancellation_requested' => 'bx-error-circle',
            'order.modification_requested' => 'bx-edit-alt',
            'order.paid' => 'bx-wallet',
            'delivery.available' => 'bx-package',
            'delivery.assigned' => 'bx-user-check',
            'delivery.picked_up' => 'bx-cycling',
            'delivery.completed' => 'bx-check-shield',
            'developer.realtime_test', 'developer.livewire_test' => 'bx-test-tube',
            default => match ($this->category) {
                'tables' => 'bx-table',
                'delivery' => 'bx-cycling',
                'system' => 'bx-info-circle',
                default => 'bx-bell',
            },
        };
    }

    public function getToneAttribute(): string
    {
        return match ($this->event_key) {
            'order.ready', 'delivery.available' => 'ready',
            'order.cancelled' => 'danger',
            'order.cancellation_requested' => 'danger',
            'order.modification_requested' => 'order',
            'order.paid', 'delivery.completed' => 'success',
            'delivery.assigned', 'delivery.picked_up' => 'delivery',
            'developer.realtime_test', 'developer.livewire_test' => 'system',
            default => $this->category === 'tables' ? 'table' : 'order',
        };
    }
}

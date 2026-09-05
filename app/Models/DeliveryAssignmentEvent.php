<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAssignmentEvent extends Model
{
    protected $fillable = [
        'delivery_assignment_id',
        'order_id',
        'from_driver_id',
        'to_driver_id',
        'actor_id',
        'event_type',
        'reason',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(DeliveryAssignment::class, 'delivery_assignment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fromDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_driver_id')->withTrashed();
    }

    public function toDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_driver_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}

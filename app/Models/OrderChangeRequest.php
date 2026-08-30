<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderChangeRequest extends Model
{
    public const TYPE_CANCELLATION = 'cancellation';

    public const TYPE_MODIFICATION = 'modification';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id', 'requested_by', 'type', 'status', 'reason',
        'original_snapshot', 'proposed_changes', 'original_total', 'proposed_total',
        'reviewed_by', 'reviewer_notes', 'reviewed_at', 'applied_at',
    ];

    protected $casts = [
        'original_snapshot' => 'array',
        'proposed_changes' => 'array',
        'original_total' => 'decimal:2',
        'proposed_total' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(OrderRefund::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->scope) {
            'full' => 'Cancelación total',
            'partial' => 'Cancelación parcial',
            'adjustment' => 'Ajuste de pedido',
            default => $this->type === self::TYPE_CANCELLATION ? 'Cancelación' : 'Modificación',
        };
    }

    public function getScopeAttribute(): ?string
    {
        return data_get($this->proposed_changes, 'request_context.scope');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            default => 'Pendiente',
        };
    }
}

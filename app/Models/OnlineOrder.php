<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'cart_snapshot' => 'array',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'cash_tendered' => 'decimal:2',
        'whatsapp_opened_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'awaiting_whatsapp' => 'Falta enviar WhatsApp',
            'pending_confirmation' => 'Esperando confirmación',
            'confirmed' => 'Confirmado',
            'rejected' => 'No concretado',
            default => $this->status,
        };
    }

    public function getFulfillmentLabelAttribute(): string
    {
        return $this->fulfillment === 'delivery' ? 'A domicilio' : 'Pasa a recoger';
    }

    public function getDisplayFolioAttribute(): string
    {
        return 'Orden número -'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT).'- MenuDigital';
    }
}

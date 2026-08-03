<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'customer_id', 'created_by',
        'customer_name', 'customer_phone', 'customer_email',
        'guests', 'occasion', 'reserved_at', 'notes',
        'status', 'source', 'is_waitlist', 'public_token', 'cancellation_reason',
    ];

    protected $casts = [
        'reserved_at' => 'datetime',
        'guests' => 'integer',
        'is_waitlist' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pendiente' => '#f59e0b',
            'confirmada' => '#10b981',
            'completada' => '#6366f1',
            'cancelada' => '#ef4444',
            default => '#6b7280',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pendiente' => 'Pendiente',
            'confirmada' => 'Confirmada',
            'completada' => 'Completada',
            'cancelada' => 'Cancelada',
            default => $this->status,
        };
    }

    public function toCalendarEvent(): array
    {
        return [
            'id' => $this->id,
            'title' => ($this->is_waitlist ? 'EN ESPERA · ' : '').$this->reserved_at->format('g:i A').' · '.$this->customer_name.' · '.$this->guests.' '.($this->guests === 1 ? 'persona' : 'personas'),
            'start' => $this->reserved_at->toIso8601String(),
            'backgroundColor' => $this->is_waitlist ? '#b45309' : $this->status_color,
            'borderColor' => $this->is_waitlist ? '#92400e' : $this->status_color,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'status' => $this->status,
                'status_label' => $this->status_label,
                'guests' => $this->guests,
                'phone' => $this->customer_phone,
                'email' => $this->customer_email,
                'occasion' => $this->occasion,
                'source' => $this->source,
                'is_waitlist' => $this->is_waitlist,
                'notes' => $this->notes,
                'customer_name' => $this->customer_name,
            ],
        ];
    }
}

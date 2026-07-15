<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mesa extends Model
{
    protected $fillable = [
        'area_id', 'mesa_group_id', 'number', 'name',
        'capacity', 'status', 'notes',
    ];

    // ── Relations ──

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MesaGroup::class, 'mesa_group_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MesaAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(MesaAssignment::class)->whereNull('released_at')->latest('assigned_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->hasMany(Order::class)->whereIn('status', ['pendiente', 'en_preparacion']);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(MesaSplit::class);
    }

    // ── Accessors ──

    public function getDisplayNameAttribute(): string
    {
        return $this->name ? "Mesa {$this->number} – {$this->name}" : "Mesa {$this->number}";
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'disponible' => '#22c55e',
            'ocupada'    => '#3b82f6',
            'reservada'  => '#8b5cf6',
            'en_cuenta'  => '#f59e0b',
            'bloqueada'  => '#6b7280',
            default      => '#9ca3af',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'disponible' => 'Disponible',
            'ocupada'    => 'Ocupada',
            'reservada'  => 'Reservada',
            'en_cuenta'  => 'En cuenta',
            'bloqueada'  => 'Bloqueada',
            default      => ucfirst($this->status),
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'disponible' => 'bx-check-circle',
            'ocupada'    => 'bx-user-check',
            'reservada'  => 'bx-calendar',
            'en_cuenta'  => 'bx-receipt',
            'bloqueada'  => 'bx-lock',
            default      => 'bx-circle',
        };
    }

    public function getIsGroupedAttribute(): bool
    {
        return $this->mesa_group_id !== null;
    }
}

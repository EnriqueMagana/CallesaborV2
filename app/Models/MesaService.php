<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MesaService extends Model
{
    public const ACTIVE_STATUSES = ['abierta', 'en_cuenta'];

    protected $fillable = [
        'cash_register_id',
        'primary_mesa_id',
        'mesa_group_id',
        'opened_by',
        'closed_by',
        'kiosk_terminal_id',
        'source',
        'status',
        'service_label',
        'opener_name_snapshot',
        'group_name_snapshot',
        'total_snapshot',
        'close_reason',
        'opened_at',
        'in_account_at',
        'closed_at',
    ];

    protected $casts = [
        'total_snapshot' => 'decimal:2',
        'opened_at' => 'datetime',
        'in_account_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function primaryMesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'primary_mesa_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MesaGroup::class, 'mesa_group_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function kioskTerminal(): BelongsTo
    {
        return $this->belongsTo(KioskTerminal::class);
    }

    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(Mesa::class, 'mesa_service_mesa')
            ->withPivot(['mesa_label_snapshot', 'is_primary'])
            ->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MesaAssignment::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(MesaSplit::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function getDurationLabelAttribute(): string
    {
        $end = $this->closed_at ?? now();
        $minutes = max(0, (int) floor($this->opened_at->diffInMinutes($end)));

        return $minutes >= 60
            ? intdiv($minutes, 60).'h '.($minutes % 60).'min'
            : $minutes.'min';
    }

    public function getIsGroupedAttribute(): bool
    {
        return $this->group_name_snapshot !== null || $this->mesas->count() > 1;
    }
}

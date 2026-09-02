<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MesaHelpRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'mesa_id', 'mesa_group_id', 'requested_by', 'requested_user_id',
        'scope', 'status', 'message', 'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MesaGroup::class, 'mesa_group_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requestedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->scope === 'group' ? 'Grupo de mesas' : 'Mesa';
    }
}

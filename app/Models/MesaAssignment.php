<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MesaAssignment extends Model
{
    protected $fillable = [
        'mesa_id', 'mesa_service_id', 'user_id', 'assignment_type', 'assigned_by',
        'released_by', 'assigned_at', 'released_at', 'release_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function mesaService(): BelongsTo
    {
        return $this->belongsTo(MesaService::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->released_at === null;
    }

    public function getAssignmentTypeLabelAttribute(): string
    {
        return $this->assignment_type === 'support' ? 'Apoyo' : 'Responsable';
    }

    public function getDurationAttribute(): string
    {
        $end = $this->released_at ?? now();
        $diff = $this->assigned_at->diff($end);

        if ($diff->h > 0) {
            return "{$diff->h}h {$diff->i}min";
        }

        return "{$diff->i}min";
    }
}

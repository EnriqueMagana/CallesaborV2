<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MesaSplit extends Model
{
    protected $fillable = ['mesa_id', 'created_by', 'split_data', 'status', 'total', 'notes'];

    protected $casts = [
        'split_data' => 'array',
        'total'      => 'decimal:2',
    ];

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pendiente'   => 'Pendiente',
            'parcial'     => 'Parcial',
            'completado'  => 'Completado',
            default       => ucfirst($this->status),
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MesaGroup extends Model
{
    protected $fillable = ['area_id', 'name'];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    public function getTotalCapacityAttribute(): int
    {
        return $this->mesas()->sum('capacity');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    protected $fillable = ['name', 'color', 'icon', 'description', 'sort_order'];

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(MesaGroup::class);
    }

    public function getMesasCountAttribute(): int
    {
        return $this->mesas()->count();
    }

    public function getAvailableCountAttribute(): int
    {
        return $this->mesas()->where('status', 'disponible')->count();
    }
}

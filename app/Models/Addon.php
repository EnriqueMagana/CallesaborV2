<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addon extends Model
{
    protected $fillable = [
        'addon_group_id', 'name', 'description',
        'image', 'extra_price', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'extra_price' => 'decimal:2',
        'is_active'   => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AddonGroup::class, 'addon_group_id');
    }
}

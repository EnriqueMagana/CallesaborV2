<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalMenuEvent extends Model
{
    public const TYPE_MENU_VIEW = 'menu_view';

    public const TYPE_PRODUCT_CLICK = 'product_click';

    protected $fillable = [
        'event_type',
        'product_id',
        'visitor_hash',
        'session_hash',
        'occurred_on',
    ];

    protected $casts = [
        'occurred_on' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

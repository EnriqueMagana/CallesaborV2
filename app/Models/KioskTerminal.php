<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KioskTerminal extends Model
{
    protected $fillable = [
        'name', 'token_hash', 'token_secret', 'token_hint', 'user_id', 'is_active', 'last_used_at',
        'allow_dine_in', 'allow_takeaway', 'allow_delivery', 'require_customer_phone',
        'orders_per_minute', 'auto_reset_seconds', 'welcome_title', 'welcome_message',
        'payment_instructions', 'success_message', 'promotion_enabled', 'promotion_badge',
        'promotion_title', 'promotion_message',
    ];

    protected $hidden = ['token_hash', 'token_secret'];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_dine_in' => 'boolean',
        'allow_takeaway' => 'boolean',
        'allow_delivery' => 'boolean',
        'require_customer_phone' => 'boolean',
        'promotion_enabled' => 'boolean',
        'last_used_at' => 'datetime',
        'token_secret' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function productPromotions(): HasMany
    {
        return $this->hasMany(KioskProductPromotion::class)->orderBy('sort_order');
    }

    public static function findByPlainToken(string $token, bool $activeOnly = true): ?self
    {
        if (strlen($token) < 32) {
            return null;
        }

        return static::query()
            ->where('token_hash', hash('sha256', $token))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->first();
    }
}

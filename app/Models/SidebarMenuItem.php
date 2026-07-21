<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SidebarMenuItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'requires_open_register' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public static function visibleTreeFor(?User $user): Collection
    {
        $registerIsOpen = CashRegister::query()->where('is_open', true)->exists();

        return static::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($query) => $query->where('is_active', true)->with([
                'children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
            ])->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (self $item) => static::filterNode($item, $user, $registerIsOpen))
            ->filter()
            ->values();
    }

    private static function filterNode(self $item, ?User $user, bool $registerIsOpen): ?self
    {
        $children = $item->children
            ->map(fn (self $child) => static::filterNode($child, $user, $registerIsOpen))
            ->filter()
            ->values();
        $item->setRelation('children', $children);
        $item->setAttribute('register_locked', $item->requires_open_register && ! $registerIsOpen);

        $allowed = ! $item->permission || ($user && $user->can($item->permission));
        if (in_array($item->type, ['section', 'group'], true)) {
            return $allowed && $children->isNotEmpty() ? $item : null;
        }

        return $allowed ? $item : null;
    }

    public function getIsCurrentAttribute(): bool
    {
        $pattern = $this->active_pattern ?: $this->route_name;

        return $pattern ? request()->routeIs($pattern) : false;
    }

    public function getResolvedUrlAttribute(): string
    {
        if ($this->route_name && \Illuminate\Support\Facades\Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return $this->url ?: '#';
    }
}

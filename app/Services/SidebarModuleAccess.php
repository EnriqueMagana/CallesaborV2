<?php

namespace App\Services;

use App\Models\SidebarMenuItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SidebarModuleAccess
{
    private ?Collection $items = null;

    public function allows(?User $user, ?string $routeName): bool
    {
        if (! $user || ! $routeName) {
            return false;
        }

        $item = $this->itemForRoute($routeName);

        if (! $item) {
            return ! $this->items()->contains(
                fn (SidebarMenuItem $candidate): bool => $candidate->type === 'link' && filled($candidate->route_name)
            );
        }

        foreach ($this->lineage($item) as $node) {
            if (! $node->is_active) {
                return false;
            }

            if ($node->permission && ! $user->can($node->permission)) {
                return false;
            }
        }

        return true;
    }

    public function itemForRoute(string $routeName): ?SidebarMenuItem
    {
        return $this->items()
            ->filter(fn (SidebarMenuItem $item): bool => $item->type === 'link' && filled($item->route_name))
            ->map(fn (SidebarMenuItem $item): array => [
                'item' => $item,
                'score' => $this->matchScore($item, $routeName),
            ])
            ->filter(fn (array $match): bool => $match['score'] > 0)
            ->sortByDesc('score')
            ->first()['item'] ?? null;
    }

    public function forget(): void
    {
        $this->items = null;
    }

    private function items(): Collection
    {
        return $this->items ??= SidebarMenuItem::query()
            ->get([
                'id',
                'parent_id',
                'type',
                'label',
                'route_name',
                'active_pattern',
                'permission',
                'is_active',
            ]);
    }

    private function lineage(SidebarMenuItem $item): Collection
    {
        $byId = $this->items()->keyBy('id');
        $lineage = collect([$item]);
        $parentId = $item->parent_id;

        while ($parentId) {
            $parent = $byId->get($parentId);
            if (! $parent) {
                break;
            }

            $lineage->push($parent);
            $parentId = $parent->parent_id;
        }

        return $lineage;
    }

    private function matchScore(SidebarMenuItem $item, string $routeName): int
    {
        if ($routeName === $item->route_name) {
            return 10000 + strlen($item->route_name);
        }

        if ($item->active_pattern && Str::is($item->active_pattern, $routeName)) {
            return 5000 + strlen(str_replace('*', '', $item->active_pattern));
        }

        if (str_starts_with($routeName, $item->route_name.'.')) {
            return 1000 + strlen($item->route_name);
        }

        return 0;
    }
}

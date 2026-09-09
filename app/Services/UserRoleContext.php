<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class UserRoleContext
{
    private const CASHIER_PERMISSIONS = [
        'ver caja',
        'abrir caja',
        'cerrar caja',
        'crear ventas en punto de venta',
        'cobrar pedidos en punto de venta',
    ];

    /**
     * Return roles in a stable business order so the most relevant role is shown first.
     */
    public function ordered(iterable $roles): Collection
    {
        return collect($roles)
            ->sortBy(fn ($role): string => sprintf(
                '%03d-%010d-%s',
                $this->priority($role),
                (int) ($role->id ?? PHP_INT_MAX),
                mb_strtolower((string) $role->name),
            ))
            ->values();
    }

    public function label(string $role): string
    {
        return str($role)->replace(['-', '_'], ' ')->squish()->title()->toString();
    }

    public function icon(string $role): string
    {
        $role = $this->normalize($role);

        return match (true) {
            $this->containsAny($role, ['owner', 'super admin']) => 'bx-crown',
            $this->containsAny($role, ['admin']) => 'bx-shield-quarter',
            $this->containsAny($role, ['gerent', 'encarg', 'supervisor']) => 'bx-briefcase',
            $this->containsAny($role, ['cajer', 'cashier']) => 'bx-money',
            $this->containsAny($role, ['delivery', 'repart', 'mensaj']) => 'bx-cycling',
            $this->containsAny($role, ['meser', 'waiter']) => 'bx-dish',
            $this->containsAny($role, ['cocin', 'chef']) => 'bx-restaurant',
            default => 'bx-shield-quarter',
        };
    }

    public function roleIcon(object $role): string
    {
        if (filled($role->icon ?? null)) {
            return (string) $role->icon;
        }

        if ($this->roleHasCashierPermissions($role)) {
            return 'bx-money';
        }

        return $this->icon((string) $role->name);
    }

    public function dashboardMode(User $user): string
    {
        $user->loadMissing('roles');
        $roles = $user->roles
            ->pluck('name')
            ->map(fn (string $role): string => $this->normalize($role));

        if ($roles->contains(fn (string $role): bool => $this->containsAny($role, ['owner', 'super admin']))) {
            return 'owner';
        }

        $hasManagementRole = $roles->contains(fn (string $role): bool => $this->containsAny(
            $role,
            ['admin', 'gerent', 'encarg', 'supervisor'],
        ));
        $hasCashierRole = $roles->contains(fn (string $role): bool => $this->containsAny($role, ['cajer', 'cashier']));
        $hasCashierCapabilities = $user->can('ver caja')
            || $user->can('abrir caja')
            || $user->can('cerrar caja')
            || $user->can('crear ventas en punto de venta')
            || $user->can('cobrar pedidos en punto de venta');

        // Caja has priority over waiter because cashier roles also operate tables.
        if ($hasManagementRole) {
            return 'admin';
        }
        if ($hasCashierRole || $hasCashierCapabilities) {
            return 'cashier';
        }
        if ($roles->contains(fn (string $role): bool => $this->containsAny($role, ['delivery', 'repart', 'mensaj']))) {
            return 'delivery';
        }
        if ($roles->contains(fn (string $role): bool => $this->containsAny($role, ['meser', 'waiter']))
            || ($user->can('ordenar mesas') && ! $user->can('ver reportes'))) {
            return 'waiter';
        }
        if (! $user->can('ver reportes')
            && ! $user->can('ver reportes financieros')
            && ! $user->can('ver ordenes')
            && ! $user->can('ver mesas')
            && ! $user->can('ver caja')
            && ! $user->can('crear ordenes')) {
            return 'restricted';
        }

        return 'admin';
    }

    private function priority(object $roleModel): int
    {
        $role = $this->normalize((string) $roleModel->name);
        $hasCashierCapabilities = $this->roleHasCashierPermissions($roleModel);

        return match (true) {
            $this->containsAny($role, ['owner', 'super admin']) => 10,
            $this->containsAny($role, ['admin']) => 20,
            $this->containsAny($role, ['gerent', 'encarg', 'supervisor']) => 30,
            $this->containsAny($role, ['cajer', 'cashier']) || $hasCashierCapabilities => 40,
            $this->containsAny($role, ['delivery', 'repart', 'mensaj']) => 50,
            $this->containsAny($role, ['meser', 'waiter']) => 60,
            $this->containsAny($role, ['cocin', 'chef']) => 70,
            default => 80,
        };
    }

    private function normalize(string $role): string
    {
        return str($role)->replace(['-', '_'], ' ')->squish()->lower()->toString();
    }

    private function containsAny(string $role, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($role, $term)) {
                return true;
            }
        }

        return false;
    }

    private function roleHasCashierPermissions(object $role): bool
    {
        if (! method_exists($role, 'relationLoaded') || ! $role->relationLoaded('permissions')) {
            return false;
        }

        return $role->permissions
            ->pluck('name')
            ->contains(fn (string $permission): bool => in_array($permission, self::CASHIER_PERMISSIONS, true));
    }
}

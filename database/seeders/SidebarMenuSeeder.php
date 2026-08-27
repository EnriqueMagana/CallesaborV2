<?php

namespace Database\Seeders;

use App\Models\SidebarMenuItem;
use Illuminate\Database\Seeder;

class SidebarMenuSeeder extends Seeder
{
    private $items;

    private $itemsBySystemKey;

    private $itemsByRoute;

    public function run(): void
    {
        $this->items = SidebarMenuItem::query()->get();
        $this->itemsBySystemKey = $this->items->whereNotNull('system_key')->keyBy('system_key');
        $this->itemsByRoute = $this->items->whereNotNull('route_name')->keyBy('route_name');

        $this->item('dashboard', [
            'label' => 'Dashboard', 'type' => 'link', 'icon' => 'bx-home-circle',
            'route_name' => 'app.dashboard', 'sort_order' => 10, 'is_system' => true,
        ]);

        $administration = $this->item('section.administration', [
            'label' => 'Administración', 'type' => 'section', 'sort_order' => 20, 'is_system' => true,
        ]);
        $this->item('admin.users', [
            'parent_id' => $administration->id, 'label' => 'Usuarios', 'type' => 'link',
            'icon' => 'bx-group', 'route_name' => 'app.usuarios', 'permission' => 'ver usuarios', 'sort_order' => 10,
        ]);
        $this->item('admin.roles', [
            'parent_id' => $administration->id, 'label' => 'Roles y permisos', 'type' => 'link',
            'icon' => 'bx-shield-quarter', 'route_name' => 'app.roles-permisos', 'permission' => 'gestionar roles', 'sort_order' => 20,
        ]);
        $configuration = $this->item('group.configuration', [
            'parent_id' => $administration->id, 'label' => 'Configuración', 'type' => 'group',
            'icon' => 'bx-cog', 'sort_order' => 30,
        ]);
        $this->item('configuration.kiosks', [
            'parent_id' => $configuration->id, 'label' => 'Kioscos', 'type' => 'link',
            'icon' => 'bx-devices', 'route_name' => 'app.kioscos', 'permission' => 'gestionar kioscos', 'sort_order' => 10,
        ]);
        $this->item('configuration.business', [
            'parent_id' => $configuration->id, 'label' => 'Datos del negocio', 'type' => 'link',
            'icon' => 'bx-store', 'route_name' => 'app.configuracion-negocio',
            'active_pattern' => 'app.configuracion-negocio', 'permission' => 'gestionar configuracion negocio', 'sort_order' => 20,
        ]);
        $this->item('configuration.sidebar', [
            'parent_id' => $configuration->id, 'label' => 'Menú lateral', 'type' => 'link',
            'icon' => 'bx-list-ul', 'route_name' => 'app.configuracion-negocio.menu',
            'active_pattern' => 'app.configuracion-negocio.menu', 'permission' => 'ver menu sidebar', 'sort_order' => 30,
        ]);

        $restaurant = $this->item('section.restaurant', [
            'label' => 'Restaurante', 'type' => 'section', 'sort_order' => 30, 'is_system' => true,
        ]);
        $this->item('restaurant.menu-builder', [
            'parent_id' => $restaurant->id, 'label' => 'Constructor de menú', 'type' => 'link',
            'icon' => 'bx-restaurant', 'route_name' => 'app.constructor-menu', 'permission' => 'ver menu', 'sort_order' => 10,
        ]);
        $this->item('restaurant.digital-menu', [
            'parent_id' => $restaurant->id, 'label' => 'Menú digital', 'type' => 'link',
            'icon' => 'bx-mobile-alt', 'route_name' => 'app.menu-digital', 'active_pattern' => 'app.menu-digital*',
            'permission' => 'gestionar menu digital', 'sort_order' => 15,
        ]);
        $this->item('restaurant.promotions', [
            'parent_id' => $restaurant->id, 'label' => 'Promociones', 'type' => 'link',
            'icon' => 'bx-purchase-tag-alt', 'route_name' => 'app.promociones',
            'active_pattern' => 'app.promociones*', 'permission' => 'ver promociones', 'sort_order' => 17,
        ]);
        $this->item('restaurant.pos', [
            'parent_id' => $restaurant->id, 'label' => 'Punto de venta', 'type' => 'link',
            'icon' => 'bx-store-alt', 'route_name' => 'app.pos', 'permission' => 'usar punto de venta', 'sort_order' => 20,
        ]);
        $operations = $this->item('group.operations', [
            'parent_id' => $restaurant->id, 'label' => 'Operación', 'type' => 'group',
            'icon' => 'bx-grid-alt', 'sort_order' => 30,
        ]);
        $this->item('operations.tables', [
            'parent_id' => $operations->id, 'label' => 'Mesas', 'type' => 'link',
            'icon' => 'bx-table', 'route_name' => 'app.mesas', 'active_pattern' => 'app.mesas*',
            'permission' => 'ver mesas', 'sort_order' => 10,
        ]);
        $this->item('operations.reservations', [
            'parent_id' => $operations->id, 'label' => 'Reservaciones', 'type' => 'link',
            'icon' => 'bx-calendar-check', 'route_name' => 'app.reservas', 'active_pattern' => 'app.reservas*',
            'permission' => 'ver reservas', 'sort_order' => 20,
        ]);
        $this->item('operations.orders', [
            'parent_id' => $operations->id, 'label' => 'Órdenes', 'type' => 'link',
            'icon' => 'bx-receipt', 'route_name' => 'app.ordenes', 'active_pattern' => 'app.ordenes*',
            'permission' => 'ver ordenes', 'sort_order' => 30,
        ]);
        $this->item('operations.customers', [
            'parent_id' => $operations->id, 'label' => 'Mis clientes', 'type' => 'link',
            'icon' => 'bx-group', 'route_name' => 'app.clientes', 'active_pattern' => 'app.clientes*',
            'permission' => 'ver clientes', 'sort_order' => 35,
        ]);
        $this->item('operations.sales-history', [
            'parent_id' => $operations->id, 'label' => 'Historial de ventas', 'type' => 'link',
            'icon' => 'bx-history', 'route_name' => 'app.historial-ventas', 'permission' => 'ver reportes', 'sort_order' => 40,
        ]);
        $this->item('operations.delivery', [
            'parent_id' => $operations->id, 'label' => 'Delivery', 'type' => 'link',
            'icon' => 'bx-cycling', 'route_name' => 'app.delivery', 'active_pattern' => 'app.delivery*',
            'permission' => 'ver delivery', 'sort_order' => 50,
        ]);
        $this->item('operations.inventory', [
            'parent_id' => $operations->id, 'label' => 'Inventario', 'type' => 'link',
            'icon' => 'bx-package', 'route_name' => 'app.inventario', 'active_pattern' => 'app.inventario*',
            'permission' => 'ver inventario', 'sort_order' => 60,
        ]);

        $cash = $this->item('group.cash', [
            'parent_id' => $restaurant->id, 'label' => 'Caja y cortes', 'type' => 'group',
            'icon' => 'bx-calculator', 'sort_order' => 40,
        ]);
        $this->item('cash.dashboard', [
            'parent_id' => $cash->id, 'label' => 'Caja', 'type' => 'link',
            'icon' => 'bx-calculator', 'route_name' => 'app.caja', 'active_pattern' => 'app.caja',
            'permission' => 'ver caja', 'sort_order' => 10,
        ]);
        $this->item('cash.cuts', [
            'parent_id' => $cash->id, 'label' => 'Cortes de caja', 'type' => 'link',
            'icon' => 'bx-history', 'route_name' => 'app.caja.cortes', 'active_pattern' => 'app.caja.cortes*',
            'permission' => 'cerrar caja', 'sort_order' => 20,
        ]);

        $development = $this->item('section.development', [
            'label' => 'Super Admin', 'type' => 'section', 'sort_order' => 40, 'is_system' => true,
            'permission' => 'ver panel super admin',
        ]);
        $this->item('development.console', [
            'parent_id' => $development->id, 'label' => 'Centro técnico', 'type' => 'link',
            'icon' => 'bx-code-alt', 'route_name' => 'app.super-admin', 'active_pattern' => 'app.super-admin',
            'permission' => 'ver panel super admin', 'sort_order' => 10, 'is_system' => true,
        ]);
        $this->item('development.pulse', [
            'parent_id' => $development->id, 'label' => 'Laravel Pulse', 'type' => 'link',
            'icon' => 'bx-pulse', 'route_name' => 'pulse',
            'permission' => 'ver panel super admin', 'sort_order' => 20, 'is_system' => true,
        ]);

        $account = $this->item('section.account', [
            'label' => 'Mi cuenta', 'type' => 'section', 'sort_order' => 50, 'is_system' => true,
        ]);
        $this->item('account.profile', [
            'parent_id' => $account->id, 'label' => 'Mi perfil', 'type' => 'link',
            'icon' => 'bx-user', 'route_name' => 'profile', 'sort_order' => 10,
        ]);

        // Estos permisos forman parte de la frontera de seguridad del módulo.
        // Se corrigen también en instalaciones existentes donde el menú ya fue sembrado.
        SidebarMenuItem::where('system_key', 'operations.reservations')
            ->update(['permission' => 'ver reservas']);
        SidebarMenuItem::where('system_key', 'operations.sales-history')
            ->update(['permission' => 'ver reportes']);
        SidebarMenuItem::where('system_key', 'restaurant.pos')
            ->update(['permission' => 'usar punto de venta']);
    }

    private function item(string $systemKey, array $defaults): SidebarMenuItem
    {
        $item = $this->itemsBySystemKey->get($systemKey);

        if (! $item && ! empty($defaults['route_name'])) {
            $item = $this->itemsByRoute->get($defaults['route_name']);
        }

        if (! $item && ($defaults['type'] ?? null) !== 'link') {
            $item = $this->items->first(fn (SidebarMenuItem $candidate): bool => $candidate->type === $defaults['type'] && $candidate->label === $defaults['label']);
        }

        if ($item) {
            $item->forceFill([
                'system_key' => $systemKey,
                'route_name' => $defaults['route_name'] ?? $item->route_name,
            ]);

            if ($item->isDirty()) {
                $item->save();
            }

            $this->remember($item);

            return $item;
        }

        $item = SidebarMenuItem::create(array_merge([
            'system_key' => $systemKey,
            'is_active' => true,
            'is_system' => false,
        ], $defaults));

        $this->items->push($item);
        $this->remember($item);

        return $item;
    }

    private function remember(SidebarMenuItem $item): void
    {
        if ($item->system_key) {
            $this->itemsBySystemKey->put($item->system_key, $item);
        }
        if ($item->route_name) {
            $this->itemsByRoute->put($item->route_name, $item);
        }
    }
}

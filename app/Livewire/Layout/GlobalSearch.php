<?php

namespace App\Livewire\Layout;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\SidebarModuleAccess;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    // Módulos con su permiso requerido (null = accesible para todos los autenticados)
    private function modules(): array
    {
        return [
            ['label' => 'Dashboard',           'icon' => 'bx-home-circle',    'route' => 'app.dashboard',        'keywords' => 'dashboard inicio home',                        'permission' => null],
            ['label' => 'Punto de Venta',      'icon' => 'bx-store-alt',      'route' => 'app.pos',              'keywords' => 'pos venta caja cobrar pedido',                  'permission' => 'usar punto de venta'],
            ['label' => 'Mesas',                'icon' => 'bx-table',          'route' => 'app.mesas',            'keywords' => 'mesas salon terraza asignar waiter mesero',     'permission' => 'ver ordenes'],
            ['label' => 'Reservaciones',       'icon' => 'bx-calendar-check', 'route' => 'app.reservas',         'keywords' => 'reservas calendario reservacion',               'permission' => 'ver reservas'],
            ['label' => 'Órdenes',             'icon' => 'bx-receipt',        'route' => 'app.ordenes',          'keywords' => 'ordenes pedidos historial',                     'permission' => 'ver ordenes'],
            ['label' => 'Mis clientes',        'icon' => 'bx-user-pin',       'route' => 'app.clientes',         'keywords' => 'clientes customer directorio telefono',          'permission' => 'ver clientes'],
            ['label' => 'Caja',                'icon' => 'bx-calculator',     'route' => 'app.caja',             'keywords' => 'caja apertura turno fondo',                     'permission' => 'ver caja'],
            ['label' => 'Cortes de caja',      'icon' => 'bx-history',        'route' => 'app.caja.cortes',      'keywords' => 'corte cierre historial caja',                   'permission' => 'cerrar caja'],
            ['label' => 'Constructor de Menú', 'icon' => 'bx-restaurant',     'route' => 'app.constructor-menu', 'keywords' => 'menu productos categorias ingredientes platos',  'permission' => 'ver menu'],
            ['label' => 'Usuarios',            'icon' => 'bx-group',          'route' => 'app.usuarios',         'keywords' => 'usuarios empleados staff personal',             'permission' => 'ver usuarios'],
            ['label' => 'Roles y Permisos',    'icon' => 'bx-shield-quarter', 'route' => 'app.roles-permisos',   'keywords' => 'roles permisos acceso configuracion',           'permission' => 'gestionar roles'],
            ['label' => 'Configuración de Kioscos', 'icon' => 'bx-devices',    'route' => 'app.kioscos',          'keywords' => 'kiosco terminal token autoservicio ajustes',     'permission' => 'gestionar kioscos'],
            ['label' => 'Configuración del negocio', 'icon' => 'bx-cog',       'route' => 'app.configuracion-negocio', 'keywords' => 'negocio empresa logo rfc whatsapp tickets impresora plantilla', 'permission' => 'gestionar configuracion negocio'],
            ['label' => 'Menú lateral',          'icon' => 'bx-list-ul',       'route' => 'app.configuracion-negocio.menu', 'keywords' => 'sidebar navegacion menu iconos ordenar agrupar', 'permission' => 'ver menu sidebar'],
            ['label' => 'Menú digital',          'icon' => 'bx-mobile-alt',    'route' => 'app.menu-digital', 'keywords' => 'menu digital banners favoritos categorias galeria publico', 'permission' => 'gestionar menu digital'],
            ['label' => 'Mi Perfil',           'icon' => 'bx-user',           'route' => 'profile',              'keywords' => 'perfil cuenta avatar contraseña',               'permission' => null],
        ];
    }

    private function can(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        // super-admin bypasa todo (Spatie Gate::before)
        if ($user->hasAnyRole(['owner', 'super-admin'])) {
            return true;
        }

        return $user->can($permission);
    }

    #[Computed]
    public function results(): array
    {
        $q = trim($this->query);

        if (strlen($q) < 2) {
            return [];
        }

        $lower = mb_strtolower($q);
        $user = auth()->user();

        // ── Módulos (instantáneo, filtrados por permiso) ──
        $nav = collect($this->modules())
            ->filter(function ($m) use ($lower, $user) {
                // Primero verifica permiso
                if ($m['permission'] !== null && ! $this->can($m['permission'])) {
                    return false;
                }
                if (! app(SidebarModuleAccess::class)->allows($user, $m['route'])) {
                    return false;
                }

                // Luego filtra por texto
                return str_contains(mb_strtolower($m['label']), $lower)
                    || str_contains(mb_strtolower($m['keywords']), $lower);
            })
            ->take(4)
            ->map(fn ($m) => [
                'type' => 'nav',
                'label' => $m['label'],
                'icon' => $m['icon'],
                'description' => 'Módulo',
                'url' => route($m['route']),
            ])
            ->values()
            ->toArray();

        // ── Órdenes (requiere 'ver ordenes') ──
        $orders = [];
        if ($this->can('ver ordenes')) {
            $activeRegisterId = CashRegister::query()
                ->where('is_open', true)
                ->latest('opened_at')
                ->value('id');

            $orders = Order::query()
                ->when($activeRegisterId, fn ($orders) => $orders->where('cash_register_id', $activeRegisterId))
                ->when(! $activeRegisterId, fn ($orders) => $orders->whereRaw('1 = 0'))
                ->where(function ($q2) use ($q) {
                    $q2->where('id', 'like', "%{$q}%")
                        ->orWhere('customer_name', 'like', "%{$q}%");
                })
                ->latest()
                ->take(3)
                ->get()
                ->map(fn ($o) => [
                    'type' => 'order',
                    'label' => '#'.$o->id.' · '.($o->customer_name ?: 'Anónimo'),
                    'icon' => 'bx-receipt',
                    'description' => '$'.number_format($o->total, 2).' · '.$o->status_label.' · '.$o->created_at->format('d/m/Y'),
                    'url' => route('app.ordenes.show', $o),
                ])
                ->toArray();
        }

        // ── Clientes (requiere 'ver ordenes') ──
        $customers = [];
        if ($this->can('ver clientes') && app(SidebarModuleAccess::class)->allows($user, 'app.clientes')) {
            $customers = Customer::where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->take(3)
                ->get()
                ->map(fn ($c) => [
                    'type' => 'customer',
                    'label' => $c->name,
                    'icon' => 'bx-user-circle',
                    'description' => $c->phone ? 'Tel: '.$c->phone : 'Cliente',
                    'url' => route('app.clientes'),
                ])
                ->toArray();
        }

        // ── Reservaciones ──
        $reservations = [];
        if ($this->can('ver reservas')) {
            $reservations = Reservation::where('customer_name', 'like', "%{$q}%")
                ->orWhere('customer_phone', 'like', "%{$q}%")
                ->latest('reserved_at')
                ->take(3)
                ->get()
                ->map(fn ($r) => [
                    'type' => 'reservation',
                    'label' => $r->customer_name,
                    'icon' => 'bx-calendar-check',
                    'description' => $r->reserved_at->format('d/m/Y g:i A').' · '.$r->guests.'p · '.$r->status_label,
                    'url' => route('app.reservas'),
                ])
                ->toArray();
        }

        // ── Productos (requiere 'ver menu') ──
        $products = [];
        if ($this->can('ver menu')) {
            $products = Product::where('name', 'like', "%{$q}%")
                ->take(3)
                ->get()
                ->map(fn ($p) => [
                    'type' => 'product',
                    'label' => $p->name,
                    'icon' => 'bx-food-menu',
                    'description' => '$'.number_format($p->price, 2),
                    'url' => route('app.constructor-menu'),
                ])
                ->toArray();
        }

        return [
            'nav' => $nav,
            'orders' => $orders,
            'customers' => $customers,
            'reservations' => $reservations,
            'products' => $products,
        ];
    }

    public function updatedQuery(): void
    {
        unset($this->results);
    }

    public function clear(): void
    {
        $this->query = '';
        unset($this->results);
    }

    public function render()
    {
        return view('livewire.layout.global-search');
    }
}

<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\KioskTerminal;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardDataBuilder
{
    public function __construct(private readonly SidebarModuleAccess $menuAccess) {}

    public function build(User $user, string $period): array
    {
        $user->loadMissing('roles');
        $openRegister = CashRegister::query()
            ->where('is_open', true)
            ->latest('opened_at')
            ->first();

        if (! $openRegister) {
            return [
                'has_open_register' => false,
            ];
        }

        $mode = $this->resolveMode($user);
        [$from, $to] = $this->periodRange($period);
        $isOwner = $mode === 'owner';
        $canViewReports = $isOwner
            || $user->can('ver reportes')
            || $user->can('ver reportes financieros');
        $canViewOperationalOrders = $isOwner
            || $user->can('ver ordenes')
            || ($mode === 'waiter' && $user->can('ordenar mesas'))
            || ($mode === 'delivery' && $user->can('ver delivery'));
        $canQueryOrders = $canViewReports || $canViewOperationalOrders;
        $canOpenOrders = ($isOwner || $user->can('ver ordenes'))
            && $this->menuAccess()->allows($user, 'app.ordenes');
        $financialAccess = $isOwner || $user->can('ver reportes financieros');

        $orders = Order::query()
            ->when($canOpenOrders, fn (Builder $query) => $query->with(['mesa.area', 'seller']))
            ->where('cash_register_id', $openRegister->id)
            ->whereBetween('created_at', [$from, $to]);

        $assignedTableIds = collect();
        if (! $canQueryOrders) {
            $orders->whereRaw('1 = 0');
        } elseif ($mode === 'waiter') {
            $assignedTableIds = MesaAssignment::query()
                ->where('user_id', $user->id)
                ->whereNull('released_at')
                ->pluck('mesa_id');

            $orders->where(function (Builder $query) use ($user, $assignedTableIds): void {
                $query->where('served_by', $user->id);
                if ($assignedTableIds->isNotEmpty()) {
                    $query->orWhereIn('mesa_id', $assignedTableIds);
                }
            });
        } elseif ($mode === 'delivery') {
            $orders->where('type', 'delivery');
        }

        $periodOrders = $orders->latest()->get();
        $statusCounts = $periodOrders->countBy('status');
        $paidOrders = $periodOrders->where('status', 'pagada');
        $profile = $this->profileCopy($mode);
        $trend = $this->trend(
            $periodOrders,
            $from,
            $to,
            $financialAccess && in_array($mode, ['owner', 'admin'], true)
        );
        $canManageKiosks = ($isOwner || $user->can('gestionar kioscos'))
            && $this->menuAccess()->allows($user, 'app.kioscos');

        return [
            'has_open_register' => true,
            'mode' => $mode,
            'profile' => $profile,
            'role_label' => $user->roles->pluck('name')
                ->map(fn (string $role) => str($role)->replace('-', ' ')->title())
                ->join(', ') ?: $profile['label'],
            'period_label' => match ($period) {
                'today' => 'Hoy',
                '30' => 'Últimos 30 días',
                default => 'Últimos 7 días',
            },
            'kpis' => $this->kpis(
                $mode,
                $periodOrders,
                $paidOrders,
                $statusCounts,
                $assignedTableIds,
                $openRegister,
                $financialAccess,
                $user
            ),
            'open_register' => $openRegister,
            'recent_orders' => $canOpenOrders ? $periodOrders->take(6) : collect(),
            'can_view_orders' => $canOpenOrders,
            'show_charts' => $canQueryOrders,
            'show_team_performance' => $canViewReports,
            'financial_access' => $financialAccess,
            'team_performance' => $canViewReports
                ? $this->teamPerformance($periodOrders, $financialAccess, $openRegister->id)
                : null,
            'quick_actions' => $this->quickActions($mode, $user),
            'can_manage_kiosks' => $canManageKiosks,
            'active_kiosks' => $canManageKiosks
                ? KioskTerminal::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'last_used_at'])
                : collect(),
            'chart_data' => [
                'trend' => [
                    'labels' => $trend['labels'],
                    'values' => $trend['values'],
                    'name' => $trend['name'],
                    'money' => $trend['money'],
                ],
                'status' => [
                    'labels' => ['Recibidos', 'Preparando', 'Listos', 'Pagados'],
                    'values' => [
                        (int) ($statusCounts['pendiente'] ?? 0),
                        (int) ($statusCounts['en_preparacion'] ?? 0),
                        (int) ($statusCounts['lista'] ?? 0),
                        (int) ($statusCounts['pagada'] ?? 0),
                    ],
                ],
            ],
        ];
    }

    private function resolveMode(User $user): string
    {
        $roles = $user->roles->pluck('name')->map(fn (string $role) => str($role)->lower()->toString());

        if ($roles->contains(fn (string $role) => in_array($role, ['owner', 'super-admin'], true))) {
            return 'owner';
        }
        if ($roles->contains(fn (string $role) => str_contains($role, 'delivery') || str_contains($role, 'repart') || str_contains($role, 'mensaj'))) {
            return 'delivery';
        }
        if ($roles->contains(fn (string $role) => str_contains($role, 'meser')) || ($user->can('ordenar mesas') && ! $user->can('ver reportes'))) {
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

    private function periodRange(string $period): array
    {
        $to = now()->endOfDay();
        $from = match ($period) {
            'today' => now()->startOfDay(),
            '30' => now()->subDays(29)->startOfDay(),
            default => now()->subDays(6)->startOfDay(),
        };

        return [$from, $to];
    }

    private function profileCopy(string $mode): array
    {
        return match ($mode) {
            'owner' => ['label' => 'Owner', 'title' => 'Visión general del negocio', 'subtitle' => 'Ventas, operación y puntos que requieren tu atención.', 'icon' => 'bx-line-chart'],
            'waiter' => ['label' => 'Mesero', 'title' => 'Tu turno, organizado', 'subtitle' => 'Tus mesas, pedidos y servicios listos en un solo lugar.', 'icon' => 'bx-dish'],
            'delivery' => ['label' => 'Delivery', 'title' => 'Entregas del turno', 'subtitle' => 'Consulta lo que está listo, en camino y pendiente de cobro.', 'icon' => 'bx-cycling'],
            'restricted' => ['label' => 'Acceso limitado', 'title' => 'Tu espacio de trabajo', 'subtitle' => 'Los indicadores aparecen cuando se asignan permisos operativos o de reportes.', 'icon' => 'bx-lock-alt'],
            default => ['label' => 'Administración', 'title' => 'Operación en tiempo real', 'subtitle' => 'Prioridades de caja, cocina y atención para mantener el servicio fluido.', 'icon' => 'bx-grid-alt'],
        };
    }

    private function kpis(
        string $mode,
        Collection $orders,
        Collection $paidOrders,
        Collection $statuses,
        Collection $assignedTableIds,
        CashRegister $openRegister,
        bool $financialAccess,
        User $user
    ): array {
        if ($mode === 'restricted') {
            return [
                $this->kpi('Indicadores disponibles', 0, 'bx-lock-alt', 'neutral', 'Solicita acceso operativo o de reportes'),
                $this->kpi('Pedidos visibles', 0, 'bx-receipt', 'neutral', 'Sin permiso para consultar pedidos'),
                $this->kpi('Mesas visibles', 0, 'bx-table', 'neutral', 'Sin permiso para consultar mesas'),
                $this->kpi('Caja', 'Restringida', 'bx-wallet', 'neutral', 'Sin permiso para consultar caja'),
            ];
        }

        if ($mode === 'waiter') {
            return [
                $this->kpi('Mis mesas activas', $assignedTableIds->count(), 'bx-table', 'primary', 'Asignadas a tu turno'),
                $this->kpi('Pedidos activos', $orders->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'en_reparto'])->count(), 'bx-receipt', 'info', 'Aún requieren atención'),
                $this->kpi('Listos para servir', (int) ($statuses['lista'] ?? 0), 'bx-bell', 'success', 'Prioridad de entrega'),
                $this->kpi('Servicios completados', $paidOrders->count(), 'bx-check-circle', 'neutral', 'En el periodo seleccionado'),
            ];
        }

        if ($mode === 'delivery') {
            return [
                $this->kpi('Listos para salir', (int) ($statuses['lista'] ?? 0), 'bx-package', 'success', 'Pedidos preparados'),
                $this->kpi('En preparación', (int) ($statuses['en_preparacion'] ?? 0), 'bx-time-five', 'warning', 'Aún en cocina'),
                $this->kpi('Contra entrega', $orders->where('delivery_method', 'contra_entrega')->whereNotIn('status', ['pagada', 'cancelada'])->count(), 'bx-wallet', 'primary', 'Cobro pendiente'),
                $this->kpi('Entregados', $orders->whereIn('status', ['entregada', 'pagada'])->count(), 'bx-check-shield', 'info', 'En el periodo seleccionado'),
            ];
        }

        $salesValue = $financialAccess ? '$'.number_format((float) $paidOrders->sum('total'), 2) : $paidOrders->count();
        $canViewTables = $user->can('ver mesas') && $this->menuAccess()->allows($user, 'app.mesas');

        return [
            $this->kpi($financialAccess ? 'Ventas cobradas' : 'Órdenes cobradas', $salesValue, 'bx-trending-up', 'success', $financialAccess ? 'Ingresos del periodo' : 'Operación completada'),
            $this->kpi('Pedidos activos', $orders->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'en_reparto'])->count(), 'bx-receipt', 'primary', 'Pendientes de completar'),
            $this->kpi(
                $canViewTables ? 'Mesas ocupadas' : 'Pedidos listos',
                $canViewTables ? Mesa::query()->whereIn('status', ['ocupada', 'en_cuenta'])->count() : (int) ($statuses['lista'] ?? 0),
                $canViewTables ? 'bx-table' : 'bx-check-circle',
                'info',
                $canViewTables ? 'Ocupadas o por cobrar' : 'Esperan entrega'
            ),
            $this->kpi('Caja', 'Abierta', 'bx-wallet', 'success', 'Desde '.$openRegister->opened_at?->format('H:i')),
        ];
    }

    private function kpi(string $label, string|int $value, string $icon, string $tone, string $help): array
    {
        return compact('label', 'value', 'icon', 'tone', 'help');
    }

    private function trend(Collection $orders, Carbon $from, Carbon $to, bool $money): array
    {
        $days = collect();
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $days->push($day->copy());
        }

        $ordersByDay = $orders->groupBy(fn (Order $order) => $order->created_at->toDateString());

        return [
            'labels' => $days->map(fn (Carbon $day) => $day->translatedFormat('d M'))->all(),
            'values' => $days->map(function (Carbon $day) use ($ordersByDay, $money) {
                $daily = $ordersByDay->get($day->toDateString(), collect());

                return $money ? round((float) $daily->where('status', 'pagada')->sum('total'), 2) : $daily->count();
            })->all(),
            'name' => $money ? 'Ventas' : 'Pedidos',
            'money' => $money,
        ];
    }

    private function teamPerformance(Collection $periodOrders, bool $financialAccess, int $cashRegisterId): array
    {
        $from = now()->startOfDay();
        $to = now()->endOfDay();
        $todayOrders = $periodOrders
            ->filter(fn (Order $order): bool => $order->created_at->betweenIncluded($from, $to))
            ->where('status', '!=', 'cancelada');

        $leaderColumns = [
            'users.id',
            'users.name',
            DB::raw('COUNT(orders.id) as orders_count'),
        ];
        if ($financialAccess) {
            $leaderColumns[] = DB::raw("SUM(CASE WHEN orders.status = 'pagada' THEN orders.total ELSE 0 END) as sales_total");
        }

        $leaders = Order::query()
            ->join('users', 'users.id', '=', 'orders.served_by')
            ->where('orders.cash_register_id', $cashRegisterId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelada')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get($leaderColumns)
            ->map(fn ($row, int $index): array => [
                'rank' => $index + 1,
                'name' => $row->name,
                'orders' => (int) $row->orders_count,
                'sales' => $financialAccess ? (float) $row->sales_total : null,
            ]);

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.cash_register_id', $cashRegisterId)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelada')
            ->where('order_items.is_cancelled', false)
            ->select('order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as units')
            ->groupBy('order_items.product_name')
            ->orderByDesc('units')
            ->limit(4)
            ->get()
            ->map(fn ($row): array => [
                'name' => $row->product_name,
                'units' => (int) $row->units,
            ]);

        $peakHour = $todayOrders
            ->groupBy(fn (Order $order): string => $order->created_at->format('H:00'))
            ->sortByDesc(fn (Collection $orders): int => $orders->count())
            ->keys()
            ->first();
        $paidToday = $todayOrders->where('status', 'pagada');

        return [
            'leaders' => $leaders,
            'top_products' => $topProducts,
            'summary' => [
                'orders' => $todayOrders->count(),
                'completed' => $paidToday->count(),
                'peak_hour' => $peakHour ?: 'Sin actividad',
                'average_ticket' => $financialAccess && $paidToday->isNotEmpty()
                    ? (float) $paidToday->avg('total')
                    : null,
            ],
        ];
    }

    private function quickActions(string $mode, User $user): array
    {
        $actions = [];
        $add = function (string $label, string $description, string $icon, string $routeName) use (&$actions, $user): void {
            if ($this->menuAccess()->allows($user, $routeName)) {
                $actions[] = [
                    'label' => $label,
                    'description' => $description,
                    'icon' => $icon,
                    'route' => route($routeName),
                ];
            }
        };

        if ($mode === 'waiter') {
            if ($user->can('ver mesas')) {
                $add('Ver mis mesas', 'Continúa la atención de tu zona.', 'bx-table', 'app.mesas');
            }
            if ($user->can('ver ordenes')) {
                $add('Revisar pedidos', 'Consulta estados y comandas.', 'bx-receipt', 'app.ordenes');
            }
        } elseif ($mode === 'delivery') {
            if ($user->can('ver delivery')) {
                $add('Ver entregas', 'Consulta y toma pedidos de delivery.', 'bx-cycling', 'app.delivery');
            }
            if ($user->can('ver ordenes')) {
                $add('Actualizar estado', 'Revisa pedidos listos y entregados.', 'bx-refresh', 'app.ordenes');
            }
        } else {
            if ($user->can('usar punto de venta')) {
                $add('Abrir POS', 'Crea y cobra una orden.', 'bx-store', 'app.pos');
            }
            if ($user->can('ver ordenes')) {
                $add('Ver órdenes', 'Supervisa cocina y entregas.', 'bx-receipt', 'app.ordenes');
            }
            if ($user->can('ver mesas')) {
                $add('Gestionar mesas', 'Revisa ocupación y cuentas.', 'bx-table', 'app.mesas');
            }
            if ($user->can('ver caja')) {
                $add('Ir a caja', 'Consulta apertura y corte.', 'bx-wallet', 'app.caja');
            }
        }

        return array_slice($actions, 0, 4);
    }

    private function menuAccess(): SidebarModuleAccess
    {
        return $this->menuAccess;
    }
}

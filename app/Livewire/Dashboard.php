<?php

namespace App\Livewire;

use App\Models\CashRegister;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public string $period = '7';

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['today', '7', '30'], true)) {
            $this->period = $period;
        }
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('dashboard-refreshed');
    }

    public function render()
    {
        return view('livewire.pages.dashboard', [
            'dashboard' => $this->buildDashboard(),
        ])->layout('layouts.app');
    }

    private function buildDashboard(): array
    {
        $user = auth()->user()->loadMissing('roles');
        $mode = $this->resolveMode($user);
        [$from, $to] = $this->periodRange();

        $orders = Order::query()
            ->with(['mesa.area', 'seller'])
            ->whereBetween('created_at', [$from, $to]);

        $assignedTableIds = collect();
        if ($mode === 'waiter') {
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

        $periodOrders = (clone $orders)->latest()->get();
        $openRegister = CashRegister::query()->where('is_open', true)->latest('opened_at')->first();
        $statusCounts = $periodOrders->countBy('status');
        $paidOrders = $periodOrders->where('status', 'pagada');
        $financialAccess = $mode === 'owner' || $user->can('ver reportes financieros');

        $profile = $this->profileCopy($mode);
        $kpis = $this->kpis($mode, $periodOrders, $paidOrders, $statusCounts, $assignedTableIds, $openRegister, $financialAccess);
        $trend = $this->trend($periodOrders, $from, $to, $financialAccess && in_array($mode, ['owner', 'admin'], true));

        return [
            'mode' => $mode,
            'profile' => $profile,
            'role_label' => $user->roles->pluck('name')->map(fn (string $role) => str($role)->replace('-', ' ')->title())->join(', ') ?: $profile['label'],
            'period_label' => match ($this->period) {
                'today' => 'Hoy',
                '30' => 'Últimos 30 días',
                default => 'Últimos 7 días',
            },
            'kpis' => $kpis,
            'open_register' => $openRegister,
            'recent_orders' => $periodOrders->take(6),
            'quick_actions' => $this->quickActions($mode, $user),
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

    private function resolveMode($user): string
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

        return 'admin';
    }

    private function periodRange(): array
    {
        $to = now()->endOfDay();
        $from = match ($this->period) {
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
            default => ['label' => 'Administración', 'title' => 'Operación en tiempo real', 'subtitle' => 'Prioridades de caja, cocina y atención para mantener el servicio fluido.', 'icon' => 'bx-grid-alt'],
        };
    }

    private function kpis(string $mode, Collection $orders, Collection $paidOrders, Collection $statuses, Collection $assignedTableIds, $openRegister, bool $financialAccess): array
    {
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

        return [
            $this->kpi($financialAccess ? 'Ventas cobradas' : 'Órdenes cobradas', $salesValue, 'bx-trending-up', 'success', $financialAccess ? 'Ingresos del periodo' : 'Operación completada'),
            $this->kpi('Pedidos activos', $orders->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'en_reparto'])->count(), 'bx-receipt', 'primary', 'Pendientes de completar'),
            $this->kpi('Mesas ocupadas', Mesa::query()->whereIn('status', ['ocupada', 'en_cuenta'])->count(), 'bx-table', 'info', 'Ocupadas o por cobrar'),
            $this->kpi('Caja', $openRegister ? 'Abierta' : 'Cerrada', 'bx-wallet', $openRegister ? 'success' : 'warning', $openRegister ? 'Desde '.$openRegister->opened_at?->format('H:i') : 'Requiere apertura'),
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

        return [
            'labels' => $days->map(fn (Carbon $day) => $day->translatedFormat('d M'))->all(),
            'values' => $days->map(function (Carbon $day) use ($orders, $money) {
                $daily = $orders->filter(fn (Order $order) => $order->created_at->isSameDay($day));
                return $money ? round((float) $daily->where('status', 'pagada')->sum('total'), 2) : $daily->count();
            })->all(),
            'name' => $money ? 'Ventas' : 'Pedidos',
            'money' => $money,
        ];
    }

    private function quickActions(string $mode, $user): array
    {
        $actions = [];
        $add = function (string $label, string $description, string $icon, string $route) use (&$actions): void {
            $actions[] = compact('label', 'description', 'icon', 'route');
        };

        if ($mode === 'waiter') {
            $add('Ver mis mesas', 'Continúa la atención de tu zona.', 'bx-table', route('app.mesas'));
            $add('Revisar pedidos', 'Consulta estados y comandas.', 'bx-receipt', route('app.ordenes'));
        } elseif ($mode === 'delivery') {
            $add('Ver entregas', 'Consulta y toma pedidos de delivery.', 'bx-cycling', route('app.delivery'));
            $add('Actualizar estado', 'Revisa pedidos listos y entregados.', 'bx-refresh', route('app.ordenes'));
        } else {
            if ($user->can('crear ordenes')) $add('Abrir POS', 'Crea y cobra una orden.', 'bx-store', route('app.pos'));
            if ($user->can('ver ordenes')) $add('Ver órdenes', 'Supervisa cocina y entregas.', 'bx-receipt', route('app.ordenes'));
            if ($user->can('ver mesas')) $add('Gestionar mesas', 'Revisa ocupación y cuentas.', 'bx-table', route('app.mesas'));
            if ($user->can('ver caja')) $add('Ir a caja', 'Consulta apertura y corte.', 'bx-wallet', route('app.caja'));
        }

        return array_slice($actions, 0, 4);
    }
}

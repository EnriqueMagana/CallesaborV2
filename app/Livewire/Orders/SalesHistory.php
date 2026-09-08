<?php

namespace App\Livewire\Orders;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class SalesHistory extends Component
{
    use WithPagination;

    public bool $hasSearched = false;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $datePreset = 'last_30_days';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $priceRange = '';

    public string $minimumTotal = '';

    public string $maximumTotal = '';

    public string $productId = '';

    public string $cashRegisterId = '';

    public string $sortBy = 'newest';

    public int $perPage = 20;

    public ?int $expandedOrderId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('ver reportes'), 403);
    }

    #[Computed]
    public function registers(): Collection
    {
        return CashRegister::query()
            ->latest('opened_at')
            ->get(['id', 'name', 'is_open', 'opened_at', 'closed_at']);
    }

    #[Computed]
    public function products(): Collection
    {
        return OrderItem::query()
            ->whereNotNull('product_id')
            ->select(['product_id', 'product_name'])
            ->groupBy('product_id', 'product_name')
            ->orderBy('product_name')
            ->get();
    }

    #[Computed]
    public function canViewFinancials(): bool
    {
        return auth()->user()?->can('ver reportes financieros') ?? false;
    }

    public function runAudit(): void
    {
        $this->applyDatePreset();

        if (! $this->canViewFinancials) {
            $this->priceRange = '';
            $this->minimumTotal = '';
            $this->maximumTotal = '';
            $this->sortBy = in_array($this->sortBy, ['total_desc', 'total_asc', 'discount_desc'], true)
                ? 'newest'
                : $this->sortBy;
        }

        $this->validate([
            'datePreset' => ['required', 'in:today,last_7_days,last_30_days,last_90_days,custom,all'],
            'dateFrom' => ['nullable', 'date', 'required_if:datePreset,custom'],
            'dateTo' => ['nullable', 'date', 'required_if:datePreset,custom', 'after_or_equal:dateFrom'],
            'minimumTotal' => ['nullable', 'numeric', 'min:0'],
            'maximumTotal' => ['nullable', 'numeric', 'min:0', 'gte:minimumTotal'],
            'perPage' => ['required', 'integer', 'in:10,20,50'],
            'sortBy' => ['required', 'in:newest,oldest,total_desc,total_asc,discount_desc,duration_desc'],
        ], [
            'dateFrom.required_if' => 'Selecciona la fecha inicial del periodo.',
            'dateTo.required_if' => 'Selecciona la fecha final del periodo.',
            'dateTo.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'maximumTotal.gte' => 'El importe máximo debe ser igual o mayor al mínimo.',
        ]);

        $this->hasSearched = true;
        $this->expandedOrderId = null;
        $this->resetPage();
        $this->forgetResults();
        $this->dispatch('sales-history-updated');
    }

    public function clearAudit(): void
    {
        $this->reset([
            'hasSearched', 'search', 'statusFilter', 'typeFilter', 'dateFrom', 'dateTo',
            'priceRange', 'minimumTotal', 'maximumTotal', 'productId', 'cashRegisterId',
            'expandedOrderId',
        ]);
        $this->datePreset = 'last_30_days';
        $this->sortBy = 'newest';
        $this->perPage = 20;
        $this->resetValidation();
        $this->resetPage();
        $this->forgetResults();
        $this->dispatch('sales-history-updated');
    }

    public function updatedDatePreset(): void
    {
        if ($this->datePreset !== 'custom') {
            $this->dateFrom = '';
            $this->dateTo = '';
        }
    }

    public function updatedPriceRange(): void
    {
        if ($this->priceRange !== 'custom') {
            $this->minimumTotal = '';
            $this->maximumTotal = '';
        }
    }

    public function toggleOrder(int $orderId): void
    {
        if (! $this->hasSearched) {
            return;
        }

        $this->expandedOrderId = $this->expandedOrderId === $orderId ? null : $orderId;
    }

    #[Computed]
    public function orders(): LengthAwarePaginatorContract
    {
        if (! $this->hasSearched) {
            return new LengthAwarePaginator([], 0, $this->perPage, 1, [
                'path' => request()->url(),
                'pageName' => 'page',
            ]);
        }

        $query = $this->filteredOrders(true);
        $this->applySorting($query);

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function summary(): array
    {
        if (! $this->hasSearched) {
            return $this->emptySummary();
        }

        $filtered = $this->filteredOrders();
        $finalized = $this->filteredOrders()->finalizedForAccounting();
        $finalizedIds = (clone $finalized)->select('orders.id');
        $orders = (int) (clone $filtered)->count();
        $paidOrders = (int) (clone $finalized)->count();
        $sales = $this->canViewFinancials ? (float) (clone $finalized)->sum('total') : null;
        $discounts = $this->canViewFinancials
            ? (float) OrderItem::query()
                ->whereIn('order_id', $finalizedIds)
                ->where('is_cancelled', false)
                ->selectRaw('COALESCE(SUM(promotion_discount + discount_amount), 0) AS aggregate')
                ->value('aggregate')
            : null;

        return [
            'orders' => $orders,
            'paid_orders' => $paidOrders,
            'sales' => $sales,
            'average_ticket' => $this->canViewFinancials && $paidOrders > 0 ? $sales / $paidOrders : null,
            'discounts' => $discounts,
            'cancelled' => (int) (clone $filtered)->where('status', 'cancelada')->count(),
            'average_minutes' => $this->averageCompletionMinutes(),
        ];
    }

    #[Computed]
    public function analytics(): array
    {
        if (! $this->hasSearched) {
            return ['trend' => [], 'products' => [], 'customers' => []];
        }

        $finalized = $this->filteredOrders()->finalizedForAccounting();
        $finalizedIds = (clone $finalized)->select('orders.id');
        $trendRows = (clone $finalized)
            ->selectRaw('DATE(COALESCE(paid_at, accounted_at, created_at)) AS audit_date')
            ->selectRaw('COUNT(*) AS orders_count')
            ->when($this->canViewFinancials, fn (Builder $query) => $query->selectRaw('SUM(total) AS sales_total'))
            ->groupBy('audit_date')
            ->orderBy('audit_date')
            ->get();

        $productRows = OrderItem::query()
            ->whereIn('order_id', $finalizedIds)
            ->where('is_cancelled', false)
            ->selectRaw("COALESCE(NULLIF(product_name, ''), 'Producto sin nombre') AS label")
            ->selectRaw('SUM(quantity) AS units')
            ->selectRaw('SUM(subtotal - promotion_discount - discount_amount) AS sales_total')
            ->groupBy('label')
            ->orderByDesc('units')
            ->limit(7)
            ->get();

        $customerExpression = "COALESCE(NULLIF(customer_name, ''), 'Cliente general')";
        $customerRows = (clone $finalized)
            ->selectRaw("{$customerExpression} AS label")
            ->selectRaw('COUNT(*) AS orders_count')
            ->when($this->canViewFinancials, fn (Builder $query) => $query->selectRaw('SUM(total) AS sales_total'))
            ->groupBy('label')
            ->orderByDesc($this->canViewFinancials ? 'sales_total' : 'orders_count')
            ->limit(5)
            ->get();

        return [
            'trend' => [
                'labels' => $trendRows->map(fn ($row) => Carbon::parse($row->audit_date)->format('d/m'))->values()->all(),
                'orders' => $trendRows->pluck('orders_count')->map(fn ($value) => (int) $value)->values()->all(),
                'sales' => $this->canViewFinancials ? $trendRows->pluck('sales_total')->map(fn ($value) => round((float) $value, 2))->values()->all() : [],
            ],
            'products' => [
                'labels' => $productRows->pluck('label')->values()->all(),
                'units' => $productRows->pluck('units')->map(fn ($value) => (int) $value)->values()->all(),
                'sales' => $this->canViewFinancials ? $productRows->pluck('sales_total')->map(fn ($value) => round((float) $value, 2))->values()->all() : [],
            ],
            'customers' => [
                'labels' => $customerRows->pluck('label')->values()->all(),
                'orders' => $customerRows->pluck('orders_count')->map(fn ($value) => (int) $value)->values()->all(),
                'sales' => $this->canViewFinancials ? $customerRows->pluck('sales_total')->map(fn ($value) => round((float) $value, 2))->values()->all() : [],
            ],
        ];
    }

    public function durationLabel(Order $order): string
    {
        $end = $order->deliveryAssignment?->delivered_at
            ?? $order->mesaService?->closed_at
            ?? $order->paid_at
            ?? $order->accounted_at
            ?? $order->cancelled_at;

        if (! $end) {
            return 'En curso';
        }

        $minutes = max(0, (int) round($order->created_at->diffInMinutes($end)));

        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }

    private function filteredOrders(bool $forListing = false): Builder
    {
        $query = Order::query()
            ->when($this->cashRegisterId !== '', fn (Builder $q) => $q->where('cash_register_id', (int) $this->cashRegisterId))
            ->when($this->statusFilter === 'accounted', fn (Builder $q) => $q->finalizedForAccounting())
            ->when($this->statusFilter !== '' && $this->statusFilter !== 'accounted', fn (Builder $q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('type', $this->typeFilter))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->productId !== '', fn (Builder $q) => $q->whereHas('items', fn (Builder $items) => $items->where('product_id', (int) $this->productId)->where('is_cancelled', false)))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $rawSearch = trim($this->search);
                $term = '%'.$rawSearch.'%';
                $hasFolioPrefix = preg_match('/^#?ORD-/i', $rawSearch) === 1;
                $folioSearch = preg_replace('/^#?ORD-/i', '', $rawSearch);
                $folioSearch = ctype_digit((string) $folioSearch) ? (int) $folioSearch : null;

                $query->where(function (Builder $inner) use ($term, $folioSearch, $hasFolioPrefix): void {
                    $inner->where('id', 'like', $term)
                        ->when($folioSearch !== null, fn (Builder $folioQuery) => $hasFolioPrefix ? $folioQuery->orWhere('folio', $folioSearch) : $folioQuery->orWhere('folio', 'like', '%'.$folioSearch.'%'))
                        ->orWhere('customer_name', 'like', $term)
                        ->orWhere('customer_phone', 'like', $term)
                        ->orWhere('table_identifier', 'like', $term)
                        ->orWhereHas('seller', fn (Builder $seller) => $seller->where('name', 'like', $term))
                        ->orWhereHas('cashRegister', fn (Builder $register) => $register->where('name', 'like', $term));
                });
            });

        if ($this->canViewFinancials) {
            [$minimum, $maximum] = $this->priceBounds();
            $query
                ->when($minimum !== null, fn (Builder $q) => $q->where('total', '>=', $minimum))
                ->when($maximum !== null, fn (Builder $q) => $q->where('total', '<=', $maximum));
        }

        if ($forListing) {
            $query->with([
                'cashRegister:id,name,is_open', 'seller:id,name', 'cancelledBy:id,name',
                'mesa:id,name,number',
                'mesaService:id,service_label,opened_at,in_account_at,closed_at',
                'deliveryAssignment.driver:id,name',
                'items' => fn ($items) => $items->orderBy('id'),
            ]);

            if ($this->canViewFinancials) {
                $query->with('payments');
            }
        }

        return $query;
    }

    private function applySorting(Builder $query): void
    {
        $durationExpression = match ($query->getConnection()->getDriverName()) {
            'sqlite' => '(julianday(COALESCE(paid_at, accounted_at, cancelled_at, created_at)) - julianday(created_at)) * 86400',
            'pgsql' => 'EXTRACT(EPOCH FROM (COALESCE(paid_at, accounted_at, cancelled_at, created_at) - created_at))',
            default => 'TIMESTAMPDIFF(SECOND, created_at, COALESCE(paid_at, accounted_at, cancelled_at, created_at))',
        };

        match ($this->sortBy) {
            'oldest' => $query->oldest('created_at'),
            'total_desc' => $query->orderByDesc('total')->latest('created_at'),
            'total_asc' => $query->orderBy('total')->latest('created_at'),
            'discount_desc' => $query->orderByRaw('(subtotal - total) DESC')->latest('created_at'),
            'duration_desc' => $query->orderByRaw($durationExpression.' DESC')->latest('created_at'),
            default => $query->latest('created_at'),
        };
    }

    private function applyDatePreset(): void
    {
        $today = now()->toDateString();
        [$this->dateFrom, $this->dateTo] = match ($this->datePreset) {
            'today' => [$today, $today],
            'last_7_days' => [now()->subDays(6)->toDateString(), $today],
            'last_30_days' => [now()->subDays(29)->toDateString(), $today],
            'last_90_days' => [now()->subDays(89)->toDateString(), $today],
            'all' => ['', ''],
            default => [$this->dateFrom, $this->dateTo],
        };
    }

    private function priceBounds(): array
    {
        return match ($this->priceRange) {
            'under_200' => [null, 199.99],
            '200_499' => [200, 499.99],
            '500_999' => [500, 999.99],
            '1000_plus' => [1000, null],
            'custom' => [$this->minimumTotal !== '' ? (float) $this->minimumTotal : null, $this->maximumTotal !== '' ? (float) $this->maximumTotal : null],
            default => [null, null],
        };
    }

    private function averageCompletionMinutes(): ?int
    {
        $rows = $this->filteredOrders()
            ->where(fn (Builder $query) => $query->whereNotNull('paid_at')->orWhereNotNull('accounted_at')->orWhereNotNull('cancelled_at'))
            ->get(['created_at', 'paid_at', 'accounted_at', 'cancelled_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        return (int) round($rows->average(function (Order $order): float {
            $end = $order->paid_at ?? $order->accounted_at ?? $order->cancelled_at;

            return $order->created_at->diffInMinutes($end);
        }));
    }

    private function emptySummary(): array
    {
        return ['orders' => 0, 'paid_orders' => 0, 'sales' => null, 'average_ticket' => null, 'discounts' => null, 'cancelled' => 0, 'average_minutes' => null];
    }

    private function forgetResults(): void
    {
        unset($this->orders, $this->summary, $this->analytics);
    }

    public function render()
    {
        return view('livewire.orders.sales-history')->layout('layouts.app');
    }
}

<?php

namespace App\Livewire\Orders;

use App\Livewire\Orders\Concerns\CustomizesProducts;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderChangeRequestService;
use App\Services\ProductCustomizationService;
use App\Support\OrderChangeDraft;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Editor de productos de una orden (mini POS, estilo meseros).
 *
 * Arma el borrador de la modificación: quitar o cambiar cantidades de lo que
 * ya está en la orden y agregar productos con sus extras e ingredientes. No
 * modifica la orden: al continuar entrega el borrador al wizard, que pide el
 * motivo y envía la solicitud para autorización.
 */
class OrderProductsEditor extends Component
{
    use CustomizesProducts;

    public Order $order;

    /** @var array<int, array<string, mixed>> */
    public array $lines = [];

    public string $search = '';

    /** 'all', 'none' (sin categoría) o el id de la categoría. */
    public string $category = 'all';

    public string $source = 'detail';

    public function mount(Order $order, OrderChangeRequestService $service): void
    {
        abort_unless(auth()->user()?->can('ver ordenes'), 403);
        $activeRegisterId = CashRegister::where('is_open', true)->latest('opened_at')->value('id');
        abort_unless($activeRegisterId && $order->cash_register_id === (int) $activeRegisterId, 404);

        try {
            $service->ensureModifiable($order, auth()->user());
        } catch (AuthorizationException) {
            abort(403);
        } catch (ValidationException $exception) {
            abort(409, collect($exception->errors())->flatten()->first());
        }

        $this->order = $order->load(['items' => fn ($query) => $query->where('is_cancelled', false)->with(['addons', 'ingredients']), 'payments', 'refunds']);
        $this->source = request()->query('source') === 'list' ? 'list' : 'detail';
        $this->lines = OrderChangeDraft::load($this->order) ?? OrderChangeDraft::linesFromOrder($this->order);
    }

    // ── Catálogo ──────────────────────────────────────────────────────────

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function hasUncategorized(): bool
    {
        return $this->categories->isNotEmpty()
            && Product::query()->where('is_active', true)->whereNull('category_id')->exists();
    }

    #[Computed]
    public function products(): Collection
    {
        $term = trim($this->search);

        return Product::query()
            ->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->when($this->category === 'none', fn ($query) => $query->whereNull('category_id'))
            ->when(ctype_digit($this->category), fn ($query) => $query->where('category_id', (int) $this->category))
            ->withCount([
                'addonGroups' => fn ($query) => $query->where('is_active', true),
                'ingredients' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'image', 'is_customizable', 'category_id']);
    }

    /** @return array<int, int> product_id => unidades nuevas en el borrador */
    #[Computed]
    public function addedByProduct(): array
    {
        return collect($this->lines)
            ->where('kind', 'new')
            ->groupBy('product_id')
            ->map(fn ($lines) => (int) $lines->sum('quantity'))
            ->all();
    }

    public function setCategory(string $category): void
    {
        $this->category = $category === 'none' || ctype_digit($category) ? $category : 'all';
        unset($this->products);
    }

    public function updatedSearch(): void
    {
        unset($this->products);
    }

    // ── Borrador ──────────────────────────────────────────────────────────

    public function addProduct(int $productId, ProductCustomizationService $customization): void
    {
        $product = $customization->find($productId);
        abort_unless($product, 404);

        if ($customization->requiresCustomization($product)) {
            $this->openCustomization($product);

            return;
        }

        $this->lines = OrderChangeDraft::addNewLine($this->lines, $product->id, $product->name, $customization->build($product, [], [], 1));
        $this->persist();
    }

    public function editLine(int $index, ProductCustomizationService $customization): void
    {
        $line = $this->lines[$index] ?? null;
        abort_unless(($line['kind'] ?? null) === 'new', 404);
        $product = $customization->find((int) $line['product_id']);
        abort_unless($product, 404);

        $this->openCustomization($product, $index, [
            'addons' => $line['addons'] ?? [],
            'ingredients' => $line['ingredients'] ?? [],
            'quantity' => (int) $line['quantity'],
            'notes' => $line['notes'] ?? '',
        ]);
    }

    public function adjustLine(int $index, int $delta): void
    {
        abort_unless(isset($this->lines[$index]), 404);
        $quantity = max(0, min(ProductCustomizationService::MAX_QUANTITY, (int) $this->lines[$index]['quantity'] + $delta));

        if ($this->lines[$index]['kind'] === 'new' && $quantity === 0) {
            array_splice($this->lines, $index, 1);
        } else {
            $this->lines[$index]['quantity'] = $quantity;
        }

        $this->persist();
    }

    public function removeLine(int $index): void
    {
        abort_unless(isset($this->lines[$index]), 404);

        if ($this->lines[$index]['kind'] === 'new') {
            array_splice($this->lines, $index, 1);
        } else {
            $this->lines[$index]['quantity'] = 0;
        }

        $this->persist();
    }

    public function restoreLine(int $index): void
    {
        abort_unless(($this->lines[$index]['kind'] ?? null) === 'existing', 404);
        $this->lines[$index]['quantity'] = (int) $this->lines[$index]['original_quantity'];
        $this->persist();
    }

    public function discardChanges(): void
    {
        OrderChangeDraft::forget($this->order);
        $this->lines = OrderChangeDraft::linesFromOrder($this->order);
        $this->resetErrorBag();
        unset($this->addedByProduct);
    }

    public function continueToRequest()
    {
        if (OrderChangeDraft::total($this->lines) <= 0) {
            $this->addError('lines', 'La orden no puede quedar vacía. Para retirar todo, solicita una cancelación total.');

            return null;
        }
        if (! OrderChangeDraft::hasChanges($this->lines)) {
            $this->addError('lines', 'Agrega, quita o cambia la cantidad de al menos un producto.');

            return null;
        }

        OrderChangeDraft::save($this->order, $this->lines);

        return $this->redirectRoute('app.ordenes.solicitud', ['order' => $this->order, 'scope' => 'adjustment', 'source' => $this->source]);
    }

    // ── Totales ───────────────────────────────────────────────────────────

    /**
     * @return array{current: float, proposed: float, delta: float, net_paid: float, pending: float, refund: float, collected_on_delivery: bool, summary: array}
     */
    #[Computed]
    public function totals(): array
    {
        $current = round((float) $this->order->total, 2);
        $proposed = OrderChangeDraft::total($this->lines);
        $netPaid = $this->order->net_paid_amount;

        return [
            'current' => $current,
            'proposed' => $proposed,
            'delta' => round($proposed - $current, 2),
            'net_paid' => $netPaid,
            'pending' => $netPaid > 0.009 ? max(0, round($proposed - $netPaid, 2)) : 0.0,
            'refund' => $netPaid > 0.009 ? max(0, round($netPaid - $proposed, 2)) : 0.0,
            'collected_on_delivery' => $this->order->is_collected_on_delivery,
            'summary' => OrderChangeDraft::summary($this->lines),
        ];
    }

    protected function onProductCustomized(Product $product, array $built, ?int $replaceIndex): void
    {
        $this->lines = OrderChangeDraft::addNewLine($this->lines, $product->id, $product->name, $built, $replaceIndex);
        $this->persist();
    }

    /**
     * Guarda el borrador en cada cambio: si el usuario sale y vuelve, sigue ahí.
     */
    private function persist(): void
    {
        $this->resetErrorBag('lines');
        OrderChangeDraft::save($this->order, $this->lines);
        unset($this->addedByProduct, $this->totals);
    }

    public function render()
    {
        return view('livewire.orders.order-products-editor')->layout('layouts.app');
    }
}

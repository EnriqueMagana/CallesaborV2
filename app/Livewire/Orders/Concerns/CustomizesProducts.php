<?php

namespace App\Livewire\Orders\Concerns;

use App\Models\Product;
use App\Services\ProductCustomizationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Estado y acciones del modal de personalización (extras, ingredientes,
 * cantidad y nota). El componente decide qué hacer con la línea confirmada
 * implementando `onProductCustomized()`.
 */
trait CustomizesProducts
{
    /** Producto en el modal, o null si está cerrado. */
    public ?int $customizingProductId = null;

    /** Índice de la línea que se edita, o null si es alta. */
    public ?int $editingLineIndex = null;

    /** @var array<int, bool> addon_id => elegido */
    public array $customAddons = [];

    /** @var array<int, int> ingredient_id => cantidad */
    public array $customIngredients = [];

    public int $customQuantity = 1;

    public string $customNotes = '';

    /**
     * Recibe la línea ya validada y cotizada por el servidor.
     *
     * @param  array  $built  resultado de ProductCustomizationService::build()
     */
    abstract protected function onProductCustomized(Product $product, array $built, ?int $replaceIndex): void;

    public function toggleCustomAddon(int $addonId, ProductCustomizationService $customization): void
    {
        $group = $this->customizingProduct?->addonGroups->first(fn ($candidate) => $candidate->addons->contains('id', $addonId));
        if (! $group) {
            return;
        }

        $maximum = $customization->groupMaximum($group);
        $selectedInGroup = $group->addons->filter(fn ($addon) => ! empty($this->customAddons[$addon->id]))->count();

        if (! empty($this->customAddons[$addonId])) {
            if ($selectedInGroup <= $customization->groupMinimum($group)) {
                return;
            }
            unset($this->customAddons[$addonId]);
        } elseif ($maximum === 1) {
            // Selección única: la nueva opción reemplaza a la anterior.
            foreach ($group->addons as $addon) {
                unset($this->customAddons[$addon->id]);
            }
            $this->customAddons[$addonId] = true;
        } elseif ($selectedInGroup < $maximum) {
            $this->customAddons[$addonId] = true;
        }

        $this->resetErrorBag();
        unset($this->customPreview);
    }

    public function adjustCustomIngredient(int $ingredientId, int $delta): void
    {
        if (! $this->customizingProduct?->ingredients->contains('id', $ingredientId)) {
            return;
        }

        $quantity = max(0, min(ProductCustomizationService::MAX_QUANTITY, (int) ($this->customIngredients[$ingredientId] ?? 0) + $delta));
        if ($quantity === 0) {
            unset($this->customIngredients[$ingredientId]);
        } else {
            $this->customIngredients[$ingredientId] = $quantity;
        }

        $this->resetErrorBag();
        unset($this->customPreview);
    }

    public function adjustCustomQuantity(int $delta): void
    {
        $this->customQuantity = max(1, min(ProductCustomizationService::MAX_QUANTITY, $this->customQuantity + $delta));
        unset($this->customPreview);
    }

    public function updatedCustomNotes(): void
    {
        unset($this->customPreview);
    }

    public function confirmCustomization(ProductCustomizationService $customization): void
    {
        $product = $this->customizingProduct;
        abort_unless($product, 404);

        try {
            $built = $customization->build($product, array_keys(array_filter($this->customAddons)), $this->customIngredients, $this->customQuantity, $this->customNotes);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        }

        $this->onProductCustomized($product, $built, $this->editingLineIndex);
        $this->closeCustomization();
    }

    public function closeCustomization(): void
    {
        $this->customizingProductId = null;
        $this->editingLineIndex = null;
        $this->customAddons = [];
        $this->customIngredients = [];
        $this->customQuantity = 1;
        $this->customNotes = '';
        $this->resetErrorBag();
        unset($this->customizingProduct, $this->customPreview);
    }

    #[Computed]
    public function customizingProduct(): ?Product
    {
        return $this->customizingProductId
            ? app(ProductCustomizationService::class)->find($this->customizingProductId)
            : null;
    }

    /**
     * Precio en vivo. Si la selección aún no es válida devuelve el motivo en
     * vez de bloquear, para guiar al usuario.
     *
     * @return array{unit_total: float, subtotal: float, summary: string, error: ?string}
     */
    #[Computed]
    public function customPreview(): array
    {
        $product = $this->customizingProduct;
        if (! $product) {
            return ['unit_total' => 0.0, 'subtotal' => 0.0, 'summary' => '', 'error' => null];
        }

        try {
            $built = app(ProductCustomizationService::class)->build($product, array_keys(array_filter($this->customAddons)), $this->customIngredients, $this->customQuantity, $this->customNotes);

            return ['unit_total' => $built['unit_total'], 'subtotal' => $built['subtotal'], 'summary' => $built['summary'], 'error' => null];
        } catch (ValidationException $exception) {
            $extras = $product->addonGroups->flatMap->addons->filter(fn ($addon) => ! empty($this->customAddons[$addon->id]))->sum('extra_price')
                + $product->ingredients->sum(fn ($ingredient) => (float) $ingredient->extra_price * (int) ($this->customIngredients[$ingredient->id] ?? 0));
            $unit = round((float) $product->price + $extras, 2);

            return ['unit_total' => $unit, 'subtotal' => round($unit * $this->customQuantity, 2), 'summary' => '', 'error' => collect($exception->errors())->flatten()->first()];
        }
    }

    /**
     * @param  array{addons?: array, ingredients?: array, quantity?: int, notes?: ?string}  $preset
     */
    protected function openCustomization(Product $product, ?int $editingIndex = null, array $preset = []): void
    {
        $customization = app(ProductCustomizationService::class);

        $this->resetErrorBag();
        $this->customizingProductId = $product->id;
        $this->editingLineIndex = $editingIndex;
        $this->customAddons = collect($preset['addons'] ?? $customization->preselectedAddonIds($product))
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
        $this->customIngredients = collect($preset['ingredients'] ?? [])->map(fn ($qty) => (int) $qty)->all();
        $this->customQuantity = (int) ($preset['quantity'] ?? 1);
        $this->customNotes = (string) ($preset['notes'] ?? '');
        unset($this->customizingProduct, $this->customPreview);
    }
}

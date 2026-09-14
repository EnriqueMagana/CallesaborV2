<?php

namespace App\Livewire\Pos;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Catálogo del punto de venta.
 *
 * Vive como componente hijo por una razón de rendimiento concreta: era el 64 %
 * del HTML de cada respuesta (86.7 KB con 60 productos) y se volvía a serializar
 * en cada click, aunque su contenido no cambiara. Como hijo tiene su propio
 * ciclo de render y el padre deja de reconstruirlo al mover el carrito.
 *
 * Para que eso se sostenga, aquí no puede entrar nada que dependa del carrito:
 * el distintivo "en el pedido" lo dibuja Alpine con `cartQtyFor()`, que lee el
 * estado de la raíz del POS.
 *
 * Los clicks van al padre con `$parent.` en vez de por evento, para conservar
 * un solo viaje al servidor por toque.
 */
class Catalog extends Component
{
    /**
     * El tipo de orden decide qué promociones aplican. Es reactivo para que un
     * cambio en el carrito del padre llegue en el mismo request.
     */
    #[Reactive]
    public string $orderType = 'ventanilla';

    public string $catalogMode = 'products';

    /**
     * El padre avisa cuando cambia el tipo de orden: las promociones del modo
     * anterior pueden no existir en el nuevo, así que se vuelve a productos.
     */
    #[On('pos-order-type-changed')]
    public function resetToProducts(): void
    {
        $this->catalogMode = 'products';
        unset($this->activePromotions);
    }

    #[On('pos-catalog-refresh')]
    public function refresh(): void
    {
        unset($this->categoriesWithProducts, $this->productsWithoutCategory, $this->allCategories, $this->activePromotions);
    }

    #[Computed(persist: true, seconds: 60)]
    public function categoriesWithProducts()
    {
        return Category::query()
            ->select(['id', 'name', 'icon', 'sort_order'])
            ->with(['products' => function ($q) {
                $q->where('is_active', true)
                    ->select([
                        'id', 'category_id', 'name', 'description', 'image', 'price',
                        'is_customizable', 'max_addons', 'min_ingredients',
                        'max_ingredients', 'sort_order',
                    ])
                    ->withCount(['addonGroups', 'ingredients'])
                    ->orderBy('sort_order')->orderBy('name');
            }])
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    #[Computed(persist: true, seconds: 60)]
    public function productsWithoutCategory()
    {
        return Product::whereNull('category_id')
            ->where('is_active', true)
            ->select([
                'id', 'category_id', 'name', 'description', 'image', 'price',
                'is_customizable', 'max_addons', 'min_ingredients',
                'max_ingredients', 'sort_order',
            ])
            ->withCount(['addonGroups', 'ingredients'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allCategories()
    {
        return $this->categoriesWithProducts
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->values();
    }

    #[Computed]
    public function activePromotions()
    {
        $fulfillment = $this->promotionFulfillment();

        return Cache::remember(
            Promotion::posCacheKey($fulfillment),
            60,
            fn () => $this->queryActivePromotions($fulfillment),
        );
    }

    public function render()
    {
        return view('livewire.pos.catalog');
    }

    private function promotionFulfillment(): string
    {
        return match ($this->orderType) {
            'delivery' => 'delivery',
            'pick_up' => 'pickup',
            default => 'takeaway',
        };
    }

    private function queryActivePromotions(string $fulfillment)
    {
        $relations = [
            'primaryProduct' => fn ($query) => $query
                ->select(['id', 'name', 'image', 'price', 'is_active', 'is_customizable'])
                ->withCount(['addonGroups', 'ingredients']),
            'groups.products' => fn ($query) => $query
                ->where('is_active', true)
                ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
            'groups.products.category.printArea',
        ];

        $manual = Promotion::query()
            ->available('pos')
            ->forAnyFulfillment(Promotion::POS_FULFILLMENT_MODES)
            ->with($relations)
            ->get();

        $automatic = Promotion::query()
            ->automaticPricingAvailable('pos', null, $fulfillment)
            ->with($relations)
            ->get();

        return $manual->concat($automatic)->unique('id')->sortBy('name')->values();
    }
}

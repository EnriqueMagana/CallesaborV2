<?php

namespace App\Livewire\Pos\Concerns;

use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionPricingService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Promociones y descuentos automaticos.
 *
 * Reglas de negocio puras: que promocion aplica, con que cantidades y a que
 * precio. El plan es explicito en que nada de esto puede mudarse al cliente — el
 * navegador solo anticipa el resultado visual.
 */
trait ManagesPromotions
{
    #[Computed]
    public function promotionOpportunities(): array
    {
        return app(PromotionPricingService::class)->opportunities(
            $this->cart,
            'pos',
            $this->promotionFulfillmentForOrderType($this->orderType)
        );
    }

    #[Computed]
    public function customizingPromotion(): ?Promotion
    {
        if (! $this->customizingPromotionId) {
            return null;
        }

        return Promotion::query()
            ->available('pos')
            ->forAnyFulfillment(Promotion::POS_FULFILLMENT_MODES)
            ->with(['groups.products' => fn ($query) => $query
                ->where('is_active', true)
                ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
                'groups.products.category.printArea'])
            ->find($this->customizingPromotionId);
    }

    #[Computed]
    public function automaticPromotionPicker(): ?Promotion
    {
        if (! $this->automaticPromotionPickerId) {
            return null;
        }

        return Promotion::query()
            ->automaticPricingAvailable('pos', null, $this->promotionFulfillmentForOrderType($this->orderType))
            ->with([
                'primaryProduct:id,name,image,price,is_active,is_customizable',
                'groups.products' => fn ($query) => $query->where('is_active', true)
                    ->select(['products.id', 'products.name', 'products.image', 'products.price', 'products.is_customizable']),
            ])
            ->find($this->automaticPromotionPickerId);
    }

    public function selectPromotionFromCatalog(int $promotionId): void
    {
        // El catálogo deshabilita la tarjeta pulsada hasta recibir esto, para que
        // un doble toque no agregue dos veces.
        $this->dispatch('pos-catalog-settled');

        $fulfillment = $this->promotionFulfillmentForOrderType($this->orderType);
        $promotion = Promotion::query()->with(['primaryProduct', 'groups.products' => fn ($query) => $query->where('is_active', true)])->find($promotionId);
        if (! $promotion) {
            $this->dispatch('notify', type: 'warning', message: 'Esta promoción ya no está disponible.');

            return;
        }

        if (! $promotion->hasAutomaticPricingRule()) {
            $this->openPromotionModal($promotionId);

            return;
        }

        $available = Promotion::query()
            ->automaticPricingAvailable('pos', null, $fulfillment)
            ->whereKey($promotionId)
            ->exists();
        $eligibleProducts = $promotion->groups->flatMap->products->unique('id')->values();
        if ($eligibleProducts->isEmpty() && $promotion->primaryProduct?->is_active) {
            $eligibleProducts = collect([$promotion->primaryProduct]);
        }
        if (! $available || $eligibleProducts->isEmpty()) {
            $this->dispatch('notify', type: 'warning', message: 'Esta oferta no aplica a la modalidad actual.');

            return;
        }

        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT) {
            $this->automaticPromotionPickerId = $promotion->id;
            $this->automaticPromotionSelections = [];
            $this->automaticPromotionProductQueue = [];
            $this->automaticPromotionQueueId = null;
            $this->resetErrorBag('automaticPromotionSelection');
            unset($this->automaticPromotionPicker);

            return;
        }

        $this->openCustomizeModal((int) $eligibleProducts->first()->id);
    }

    public function closeAutomaticPromotionPicker(): void
    {
        $this->automaticPromotionPickerId = null;
        $this->automaticPromotionSelections = [];
        $this->automaticPromotionProductQueue = [];
        $this->automaticPromotionQueueId = null;
        $this->resetErrorBag('automaticPromotionSelection');
        unset($this->automaticPromotionPicker);
    }

    public function confirmActivePromotion(): void
    {
        if (! $this->activePromotionNotice) {
            return;
        }

        $promotionName = (string) ($this->activePromotionNotice['promotion_name'] ?? 'Promoción');
        $this->activePromotionNotice = null;
        $this->dispatch('notify', type: 'success', message: "{$promotionName} aplicada al pedido.");
    }

    public function closeActivePromotionNotice(): void
    {
        $this->activePromotionNotice = null;
    }

    public function addEligiblePromotionProduct(int $promotionId, int $productId): void
    {
        abort_unless($this->automaticPromotionPickerId === $promotionId, 422);
        $promotion = $this->automaticPromotionPicker;
        $eligibleIds = $this->automaticPromotionEligibleIds($promotion);
        abort_unless(in_array($productId, $eligibleIds, true), 422);

        $cycle = $this->automaticPromotionCycle($promotion);
        if (array_sum($this->automaticPromotionSelections) >= $cycle
            || ($this->automaticPromotionSelections[$productId] ?? 0) >= self::MAX_ITEM_QUANTITY) {
            return;
        }

        $this->automaticPromotionSelections[$productId] = ($this->automaticPromotionSelections[$productId] ?? 0) + 1;
        $this->resetErrorBag('automaticPromotionSelection');
    }

    public function removeEligiblePromotionProduct(int $promotionId, int $productId): void
    {
        abort_unless($this->automaticPromotionPickerId === $promotionId, 422);
        $promotion = $this->automaticPromotionPicker;
        abort_unless(in_array($productId, $this->automaticPromotionEligibleIds($promotion), true), 422);

        $quantity = (int) ($this->automaticPromotionSelections[$productId] ?? 0);
        if ($quantity <= 1) {
            unset($this->automaticPromotionSelections[$productId]);
        } else {
            $this->automaticPromotionSelections[$productId] = $quantity - 1;
        }
        $this->resetErrorBag('automaticPromotionSelection');
    }

    public function confirmAutomaticPromotionSelection(int $promotionId): void
    {
        abort_unless($this->automaticPromotionPickerId === $promotionId, 422);
        $promotion = $this->automaticPromotionPicker;
        if (! $promotion) {
            $this->closeAutomaticPromotionPicker();

            return;
        }

        $eligibleIds = $this->automaticPromotionEligibleIds($promotion);
        $selections = collect($this->automaticPromotionSelections)
            ->filter(fn ($quantity, $productId) => in_array((int) $productId, $eligibleIds, true) && is_numeric($quantity) && (int) $quantity > 0)
            ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => (int) $quantity]);
        $cycle = $this->automaticPromotionCycle($promotion);

        if ($selections->sum() !== $cycle) {
            $this->addError('automaticPromotionSelection', "Selecciona exactamente {$cycle} productos para aplicar esta promoción.");

            return;
        }

        $this->automaticPromotionProductQueue = $selections
            ->flatMap(fn (int $quantity, int $productId) => array_fill(0, $quantity, $productId))
            ->values()
            ->all();
        $this->automaticPromotionQueueId = $promotion->id;
        $this->automaticPromotionPickerId = null;
        $this->automaticPromotionSelections = [];
        $this->resetErrorBag('automaticPromotionSelection');
        unset($this->automaticPromotionPicker);

        $this->continueAutomaticPromotionSelection();
    }

    /** @return array<int, int> */
    private function automaticPromotionEligibleIds(?Promotion $promotion): array
    {
        $eligibleIds = $promotion?->groups
            ->flatMap->products
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all() ?? [];

        if ($eligibleIds === [] && $promotion?->primary_product_id) {
            return [(int) $promotion->primary_product_id];
        }

        return $eligibleIds;
    }

    private function automaticPromotionCycle(?Promotion $promotion): int
    {
        $rule = $promotion?->normalizedPricingRule() ?? [];

        return max(1, min(
            self::MAX_ITEM_QUANTITY * 2,
            (int) ($rule['buy_quantity'] ?? 1) + (int) ($rule['reward_quantity'] ?? 1)
        ));
    }

    private function continueAutomaticPromotionSelection(): void
    {
        if (! $this->automaticPromotionQueueId) {
            return;
        }

        while ($this->automaticPromotionProductQueue !== []) {
            $productId = (int) array_shift($this->automaticPromotionProductQueue);
            $this->openCustomizeModal($productId);

            if ($this->showCustomizeModal) {
                return;
            }
        }

        $this->automaticPromotionQueueId = null;
        $this->dispatch('notify', type: 'success', message: 'Productos agregados; la promoción se aplicó automáticamente.');
    }

    private function cancelAutomaticPromotionSelection(): void
    {
        $this->automaticPromotionProductQueue = [];
        $this->automaticPromotionQueueId = null;
    }

    public function completePromotionOpportunity(int $promotionId): void
    {
        $opportunity = collect($this->promotionOpportunities)->firstWhere('promotion_id', $promotionId);
        if (! $opportunity) {
            $this->dispatch('notify', type: 'info', message: 'La oferta ya fue aplicada o dejó de estar disponible.');

            return;
        }

        if (count($opportunity['eligible_product_ids'] ?? []) > 1) {
            $this->selectPromotionFromCatalog($promotionId);

            return;
        }

        $product = Product::query()
            ->where('is_active', true)
            ->withCount(['addonGroups', 'ingredients'])
            ->find($opportunity['product_id']);
        if (! $product) {
            $this->dispatch('notify', type: 'warning', message: 'El producto de la oferta ya no está disponible.');

            return;
        }

        $missing = max(1, min(self::MAX_ITEM_QUANTITY, (int) $opportunity['missing_quantity']));
        if ($product->is_customizable || $product->addon_groups_count > 0 || $product->ingredients_count > 0) {
            $this->openCustomizeModal($product->id);
            $this->itemQuantity = $missing;

            return;
        }

        for ($unit = 0; $unit < $missing; $unit++) {
            $this->addSimpleProductToCart($product);
        }
        $this->dispatch('notify', type: 'success', message: 'Producto agregado; la promoción se aplicó automáticamente.');
    }

    public function openPromotionModal(int $promotionId): void
    {
        $promotion = Promotion::query()
            ->available('pos')
            ->forAnyFulfillment(Promotion::POS_FULFILLMENT_MODES)
            ->with([
                'groups.products' => fn ($query) => $query
                    ->where('is_active', true)
                    ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
                'groups.products.category.printArea',
            ])
            ->find($promotionId);

        if (! $promotion || $promotion->groups->isEmpty()) {
            $this->dispatch('notify', type: 'warning', message: 'Esta promoción ya no está disponible.');

            return;
        }

        $this->customizingPromotionId = $promotion->id;
        $this->promotionSelections = [];
        $this->promotionQuantity = 1;
        $this->showPromotionModal = true;
        $this->resetErrorBag('promotion');
        unset($this->customizingPromotion);
    }

    public function closePromotionModal(): void
    {
        $this->showPromotionModal = false;
        $this->customizingPromotionId = null;
        $this->promotionSelections = [];
        $this->promotionQuantity = 1;
        $this->resetErrorBag('promotion');
        unset($this->customizingPromotion);
    }

    public function changePromotionSelection(int $groupId, int $productId, int $delta): void
    {
        $promotion = $this->customizingPromotion;
        $group = $promotion?->groups->firstWhere('id', $groupId);
        if (! $group || ! $group->products->contains('id', $productId)) {
            return;
        }

        $current = (int) ($this->promotionSelections[$groupId][$productId] ?? 0);
        $groupTotal = (int) collect($this->promotionSelections[$groupId] ?? [])->sum();
        if ($delta > 0 && $groupTotal >= $group->max_selections) {
            return;
        }

        $next = max(0, $current + ($delta > 0 ? 1 : -1));
        if ($next === 0) {
            unset($this->promotionSelections[$groupId][$productId]);
        } else {
            $this->promotionSelections[$groupId][$productId] = $next;
        }
        $this->resetErrorBag('promotion');
    }

    public function addPromotionToCart(): void
    {
        $promotion = $this->customizingPromotion;
        if (! $promotion) {
            $this->addError('promotion', 'Esta promoción ya no está disponible.');

            return;
        }
        if ($this->promotionQuantity < 1 || $this->promotionQuantity > self::MAX_ITEM_QUANTITY) {
            $this->addError('promotion', 'La cantidad debe estar entre 1 y 99.');

            return;
        }

        $snapshot = $this->validatedPromotionSnapshot($promotion, $this->promotionSelections);
        $this->cart[] = [
            'cart_id' => Str::uuid()->toString(),
            'product_id' => null,
            'promotion_id' => $promotion->id,
            'product_name' => $promotion->name,
            'product_price' => (float) $promotion->price,
            'product_image' => $promotion->image,
            'quantity' => $this->promotionQuantity,
            'unit_extra' => 0,
            'unit_total' => (float) $promotion->price,
            'subtotal' => (float) $promotion->price * $this->promotionQuantity,
            'notes' => '',
            'addons' => [],
            'ingredients' => [],
            'promotion_selections' => $snapshot,
        ];

        $this->closePromotionModal();
        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    private function refreshPromotionCart(?string $fulfillment = null): void
    {
        foreach ($this->cart as $index => $item) {
            if (empty($item['promotion_id']) || ! empty($item['auto_promotion_applied'])) {
                continue;
            }

            $promotion = Promotion::query()
                ->available('pos', null, $fulfillment)
                ->with([
                    'groups.products' => fn ($query) => $query
                        ->where('is_active', true)
                        ->select(['products.id', 'products.category_id', 'products.name', 'products.image']),
                    'groups.products.category.printArea',
                ])
                ->find($item['promotion_id']);
            if (! $promotion) {
                throw ValidationException::withMessages([
                    'cart' => "La promoción «{$item['product_name']}» venció o fue pausada. Retírala del pedido.",
                ]);
            }

            $selectionMap = collect($item['promotion_selections'] ?? [])->mapWithKeys(function ($group) {
                return [(int) ($group['group_id'] ?? 0) => collect($group['items'] ?? [])->mapWithKeys(
                    fn ($selected) => [(int) ($selected['product_id'] ?? 0) => (int) ($selected['quantity'] ?? 0)]
                )->all()];
            })->all();
            $snapshot = $this->validatedPromotionSnapshot($promotion, $selectionMap);
            $quantity = max(1, (int) $item['quantity']);
            $this->cart[$index]['product_name'] = $promotion->name;
            $this->cart[$index]['product_price'] = (float) $promotion->price;
            $this->cart[$index]['unit_total'] = (float) $promotion->price;
            $this->cart[$index]['subtotal'] = (float) $promotion->price * $quantity;
            $this->cart[$index]['promotion_selections'] = $snapshot;
        }

        unset($this->cartTotal, $this->cartCount);
        $this->saveCart();
    }

    private function promotionFulfillmentForOrderType(string $type): string
    {
        return match ($type) {
            'delivery' => 'delivery',
            'pick_up' => 'pickup',
            default => 'takeaway',
        };
    }

    /**
     * Opens the cashier-facing explanation only when a rule gains a new benefit.
     * Pricing remains server-authoritative; the modal confirms what was applied.
     */
    private function captureActivatedPromotion(array $before, array $after): void
    {
        $discountsBefore = $this->automaticPromotionDiscounts($before);
        $discountsAfter = $this->automaticPromotionDiscounts($after);
        $activatedPromotionId = collect($discountsAfter)
            ->map(fn (float $discount, int $promotionId) => [
                'promotion_id' => $promotionId,
                'increase' => round($discount - ($discountsBefore[$promotionId] ?? 0.0), 2),
            ])
            ->filter(fn (array $candidate) => $candidate['increase'] > 0.0)
            ->sortByDesc('increase')
            ->first();

        if (! $activatedPromotionId) {
            return;
        }

        $promotion = Promotion::query()
            ->with('groups.products:id,name')
            ->find($activatedPromotionId['promotion_id']);
        if (! $promotion) {
            return;
        }

        $eligibleProductIds = $promotion->groups->flatMap->products->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        if ($eligibleProductIds === [] && $promotion->primary_product_id) {
            $eligibleProductIds = [(int) $promotion->primary_product_id];
        }

        $eligibleLines = collect($after)->filter(
            fn (array $line) => in_array((int) ($line['product_id'] ?? 0), $eligibleProductIds, true)
                && empty($line['promotion_selections'])
        );
        $config = $promotion->normalizedPricingRule();
        $cycle = $config['buy_quantity'] + $config['reward_quantity'];
        $eligibleQuantity = (int) $eligibleLines->sum(fn (array $line) => max(1, (int) ($line['quantity'] ?? 1)));
        $applications = $promotion->pricing_rule_type === Promotion::PRICING_RULE_BUY_X_GET_Y_DISCOUNT
            ? intdiv($eligibleQuantity, $cycle)
            : $eligibleQuantity;
        if ($config['max_applications_per_order']) {
            $applications = min($applications, $config['max_applications_per_order']);
        }

        $this->activePromotionNotice = [
            'promotion_id' => $promotion->id,
            'promotion_name' => $promotion->name,
            'label' => $promotion->pricingRuleLabel(),
            'explanation' => $this->activePromotionExplanation($promotion, $config, max(1, $applications)),
            'eligible_quantity' => $eligibleQuantity,
            'application_count' => max(1, $applications),
            'new_savings' => (float) $activatedPromotionId['increase'],
            'total_savings' => (float) $discountsAfter[$promotion->id],
            'products' => $eligibleLines
                ->groupBy(fn (array $line) => (int) $line['product_id'])
                ->map(fn ($lines) => [
                    'name' => (string) ($lines->first()['product_name'] ?? 'Producto'),
                    'quantity' => (int) $lines->sum(fn (array $line) => max(1, (int) ($line['quantity'] ?? 1))),
                ])
                ->values()
                ->all(),
        ];

        $this->dispatch(
            'notify',
            type: 'success',
            message: 'Promoción activa: '.$promotion->name.'. Ahorro $'.number_format($activatedPromotionId['increase'], 2).'.'
        );
    }

    /** @return array<int, float> */
    private function automaticPromotionDiscounts(array $cart): array
    {
        return collect($cart)
            ->filter(fn (array $line) => ! empty($line['auto_promotion_applied']) && ! empty($line['promotion_id']))
            ->groupBy(fn (array $line) => (int) $line['promotion_id'])
            ->map(fn ($lines) => round((float) $lines->sum('promotion_discount'), 2))
            ->all();
    }

    private function activePromotionExplanation(Promotion $promotion, array $config, int $applications): string
    {
        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_PERCENTAGE_DISCOUNT) {
            return "Se descuenta {$config['discount_percentage']}% del precio base de cada producto participante. Los extras conservan su precio.";
        }

        if ($promotion->pricing_rule_type === Promotion::PRICING_RULE_FIXED_PRODUCT_PRICE) {
            return 'Cada producto participante queda en $'.number_format($config['fixed_price'], 2).'. Los extras se cobran por separado.';
        }

        $cycle = $config['buy_quantity'] + $config['reward_quantity'];
        $benefit = $config['reward_discount_percentage'] === 100
            ? "{$config['reward_quantity']} producto(s) de menor precio quedan sin costo"
            : "{$config['reward_quantity']} producto(s) de menor precio reciben {$config['reward_discount_percentage']}% de descuento";

        return "Por cada {$cycle} productos participantes, {$benefit}. Se completaron {$applications} aplicación(es).";
    }

    private function validatedPromotionSnapshot(Promotion $promotion, array $selections): array
    {
        $snapshot = [];
        foreach ($promotion->groups as $group) {
            $requested = collect($selections[$group->id] ?? [])
                ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => max(0, (int) $quantity)])
                ->filter();
            $allowed = $group->products->keyBy('id');
            if ($requested->keys()->diff($allowed->keys())->isNotEmpty()) {
                throw ValidationException::withMessages(['promotion' => "«{$group->name}» contiene productos no disponibles."]);
            }
            $total = (int) $requested->sum();
            if ($total < $group->min_selections || $total > $group->max_selections) {
                throw ValidationException::withMessages([
                    'promotion' => "«{$group->name}» requiere entre {$group->min_selections} y {$group->max_selections} selección(es).",
                ]);
            }

            $snapshot[] = [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'items' => $requested->map(function ($quantity, $productId) use ($allowed, $group) {
                    $product = $allowed->get((int) $productId);
                    $category = $product?->category;
                    $printArea = $category?->printArea;

                    return [
                        'product_id' => (int) $productId,
                        'product_name' => $product?->name ?? 'Producto',
                        'quantity' => (int) $quantity,
                        'category_name' => $category?->name,
                        'print_area_id' => $printArea?->id,
                        'print_area_name' => $printArea?->name ?? $category?->name ?? $group->name ?? 'General',
                    ];
                })->values()->all(),
            ];
        }

        return $snapshot;
    }
}

@props(['product', 'featured' => false, 'rank' => null])

@php
    $modalProduct = [
        'name' => $product->name,
        'description' => $product->description ?: 'Consulta los detalles y opciones disponibles de este producto.',
        'price' => '$'.number_format((float) $product->price, 2),
        'image' => $product->image ? Storage::url($product->image) : null,
        'category' => $product->category?->name ?? 'Especialidad',
        'customizable' => (bool) $product->is_customizable,
        'minIngredients' => (int) ($product->min_ingredients ?? 0),
        'maxIngredients' => $product->max_ingredients !== null ? (int) $product->max_ingredients : null,
        'maxAddons' => $product->max_addons !== null ? (int) $product->max_addons : null,
        'ingredients' => $product->ingredients->map(fn ($ingredient) => [
            'name' => $ingredient->name,
            'description' => $ingredient->description,
            'image' => $ingredient->image ? Storage::url($ingredient->image) : null,
            'extraPrice' => (float) $ingredient->extra_price > 0 ? '+$'.number_format((float) $ingredient->extra_price, 2) : null,
        ])->values(),
        'addonGroups' => $product->addonGroups->map(fn ($group) => [
            'name' => $group->name,
            'description' => $group->description,
            'required' => (bool) $group->is_required,
            'minimum' => (int) $group->min_selections,
            'maximum' => (int) $group->max_selections,
            'options' => $group->addons->map(fn ($addon) => [
                'name' => $addon->name,
                'description' => $addon->description,
                'extraPrice' => (float) $addon->extra_price > 0 ? '+$'.number_format((float) $addon->extra_price, 2) : 'Incluido',
            ])->values(),
        ])->values(),
    ];
@endphp

<article
    {{ $attributes->class(['product-card', 'product-card--featured' => $featured, 'product-card--ranked' => $rank]) }}
    data-menu-product
    data-search="{{ \Illuminate\Support\Str::lower($product->name.' '.$product->description.' '.$product->category?->name) }}"
>
    <button
        type="button"
        class="product-card__trigger"
        aria-label="Ver detalles de {{ $product->name }}"
        aria-haspopup="dialog"
        data-product-detail="{{ json_encode($modalProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    ></button>
    <div class="product-card__media">
        @if($rank)
            <span class="product-card__rank" aria-label="Favorito número {{ $rank }}">{{ $rank }}</span>
        @endif
        @if($product->image)
            <img
                src="{{ Storage::url($product->image) }}"
                alt="{{ $product->name }}"
                width="{{ $featured ? 640 : 440 }}"
                height="{{ $featured ? 400 : 330 }}"
                loading="lazy"
                decoding="async"
            >
        @else
            <span class="product-card__placeholder" aria-hidden="true"><i class="bx bx-dish"></i></span>
        @endif
        @if($featured)
            <span class="product-card__badge"><i class="bx bx-star" aria-hidden="true"></i> Destacado</span>
        @endif
    </div>
    <div class="product-card__content">
        @if($product->category)
            <span class="product-card__category">{{ $product->category->name }}</span>
        @endif
        <div class="product-card__title-row">
            <h3>{{ $product->name }}</h3>
            <strong class="product-card__price">${{ number_format((float) $product->price, 2) }}</strong>
        </div>
        @if($product->description)
            <p>{{ $product->description }}</p>
        @endif
        <span class="product-card__detail">Ver detalle <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></span>
        <span class="product-card__action" aria-hidden="true"><i class="bx bx-plus"></i></span>
    </div>
</article>

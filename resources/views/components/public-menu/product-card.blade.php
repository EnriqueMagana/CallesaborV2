@props(['product', 'featured' => false, 'rank' => null, 'badge' => null, 'imageOverride' => null, 'titleOverride' => null, 'descriptionOverride' => null, 'priceOverride' => null, 'originalPrice' => null, 'discountPercent' => null])

@php
    $cardName = $titleOverride ?: $product->name;
    $cardDescription = $descriptionOverride ?: $product->description;
    $cardImage = $imageOverride ?: $product->image;
    $cardPrice = $priceOverride !== null ? (float) $priceOverride : (float) $product->price;
    $modalProduct = [
        'name' => $cardName,
        'description' => $cardDescription ?: 'Consulta los detalles y opciones disponibles de este producto.',
        'price' => '$'.number_format($cardPrice, 2),
        'originalPrice' => $originalPrice !== null ? '$'.number_format((float) $originalPrice, 2) : null,
        'image' => $cardImage ? Storage::url($cardImage) : null,
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
    data-search="{{ \Illuminate\Support\Str::lower($cardName.' '.$cardDescription.' '.$product->category?->name) }}"
>
    <button
        type="button"
        class="product-card__trigger"
        aria-label="Ver detalles de {{ $cardName }}"
        aria-haspopup="dialog"
        data-product-detail="{{ json_encode($modalProduct, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
    ></button>
    <div @class([
        'product-card__media',
        'menu-image-shell is-image-loading' => filled($cardImage),
    ]) @if($cardImage) data-menu-image-shell @endif>
        @if($rank)
            <span class="product-card__rank" aria-label="Favorito número {{ $rank }}">{{ $rank }}</span>
        @endif
        @if($cardImage)
            <img
                src="{{ Storage::url($cardImage) }}"
                alt="{{ $cardName }}"
                width="{{ $featured ? 640 : 440 }}"
                height="{{ $featured ? 400 : 330 }}"
                loading="lazy"
                decoding="async"
                data-menu-image
            >
        @else
            <span class="product-card__placeholder" aria-hidden="true"><i class="bx bx-dish"></i></span>
        @endif
        @if(($featured || $badge) && $discountPercent === null)
            <span class="product-card__badge"><i class="bx bx-star" aria-hidden="true"></i> {{ $badge ?: 'Destacado' }}</span>
        @endif
        @if($discountPercent !== null)
            <span class="product-card__action product-card__action--discount" aria-hidden="true"><i class="bx bx-plus"></i></span>
        @endif
    </div>
    <div class="product-card__content">
        @if($discountPercent !== null)
            <div class="product-card__discount-summary">
                <h3>{{ $cardName }}</h3>
                @if($cardDescription)
                    <p>{{ $cardDescription }}</p>
                @endif
                <span class="product-card__discount-line">
                    <strong class="product-card__price">${{ number_format($cardPrice, 2) }}</strong>
                    <span class="product-card__discount-badge"><i class="bx bxs-hot" aria-hidden="true"></i>-{{ $discountPercent }}%</span>
                </span>
                @if($originalPrice !== null)
                    <del>${{ number_format((float) $originalPrice, 2) }}</del>
                @endif
            </div>
            <span class="product-card__detail">Ver detalle <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></span>
        @else
            @if($product->category)
                <span class="product-card__category">{{ $product->category->name }}</span>
            @endif
            <div class="product-card__title-row">
                <h3>{{ $cardName }}</h3>
                <span class="product-card__pricing"><strong class="product-card__price">${{ number_format($cardPrice, 2) }}</strong>@if($originalPrice !== null)<del>${{ number_format((float) $originalPrice, 2) }}</del>@endif</span>
            </div>
            @if($cardDescription)
                <p>{{ $cardDescription }}</p>
            @endif
            <span class="product-card__detail">Ver detalle <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></span>
            <span class="product-card__action" aria-hidden="true"><i class="bx bx-plus"></i></span>
        @endif
    </div>
</article>

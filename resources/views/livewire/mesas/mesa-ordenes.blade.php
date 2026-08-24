<div>
    @once
        <link rel="stylesheet" href="{{ asset('assets/css/mesas.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/css/mesa-orden.css') }}">
    @endonce

    {{-- Header --}}
    <div class="mo-header">
        <div class="mo-header-left">
            <button class="mo-back-btn" wire:click="goBack">
                <i class="bx bx-arrow-back"></i>
            </button>
            <div class="mo-mesa-info">
                <div class="mo-mesa-badge" style="background: {{ $this->mesa->status_color }}20; color: {{ $this->mesa->status_color }}">
                    <i class="bx {{ $this->mesa->status_icon }}"></i>
                    {{ $this->mesa->status_label }}
                </div>
                <h5 class="mo-mesa-title">
                    Mesa {{ $this->mesa->number }} — Órdenes activas
                    @if($this->mesa->name) <span class="fw-normal text-muted fs-6">{{ $this->mesa->name }}</span>@endif
                </h5>
            </div>
        </div>
        <div>
            <span class="badge bg-label-primary fs-6">
                Total: ${{ number_format($this->orders->sum('total'), 2) }}
            </span>
        </div>
    </div>

    @if($this->orders->isEmpty())
        <div class="mo-empty">
            <i class="bx bx-receipt"></i>
            <p>No hay órdenes activas para esta mesa.</p>
        </div>
    @else
        <div class="mo-ordenes-list">
            @foreach($this->orders as $order)
            <div class="mo-orden-card">
                <div class="mo-orden-card-header">
                    <div class="mo-orden-meta">
                        <span class="mo-order-id">#{{ $order->id }}</span>
                        <span class="badge bg-label-{{ $order->status_color }}">{{ $order->status_label }}</span>
                        @if($order->seller)
                            <span class="mo-orden-waiter">
                                <i class="bx bx-user"></i> {{ $order->seller->name }}
                            </span>
                        @endif
                        <span class="mo-orden-time text-muted">
                            <i class="bx bx-time"></i> {{ $order->created_at->format('H:i') }}
                            <small>({{ $order->created_at->diffForHumans() }})</small>
                        </span>
                    </div>
                    <span class="mo-orden-total">${{ number_format($order->total, 2) }}</span>
                </div>
                @if($order->notes)
                    <div class="mo-orden-notes">
                        <i class="bx bx-comment-detail"></i> {{ $order->notes }}
                    </div>
                @endif
                <div class="mo-orden-items">
                    @foreach($order->items as $item)
                    <div class="mo-orden-item">
                        <div class="mo-orden-item-header">
                            <span class="mo-orden-item-qty">{{ $item->quantity }}×</span>
                            <span class="mo-orden-item-name">{{ $item->product_name }}</span>
                            <span class="mo-orden-item-price">${{ number_format($item->subtotal, 2) }}</span>
                        </div>
                        @if($item->addons->count())
                            <div class="mo-orden-addons">
                                @foreach($item->addons as $addon)
                                    <span class="mo-addon-chip">
                                        <i class="bx bx-plus-circle"></i>
                                        {{ $addon->addon_name }}
                                        @if($addon->extra_price > 0)
                                            +${{ number_format($addon->extra_price, 2) }}
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if($item->ingredients->count())
                            <div class="mo-orden-addons">
                                @foreach($item->ingredients as $ing)
                                    <span class="mo-addon-chip mo-addon-chip--ing">
                                        <i class="bx bx-list-ul"></i>
                                        {{ $ing->ingredient_name }}
                                        @if($ing->quantity > 1)×{{ $ing->quantity }}@endif
                                        @if($ing->extra_price > 0)
                                            +${{ number_format($ing->extra_price * $ing->quantity, 2) }}
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        @if($item->notes)
                            <div class="mo-orden-item-note">
                                <i class="bx bx-comment"></i> {{ $item->notes }}
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

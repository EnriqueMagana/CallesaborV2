<div>
<x-pos.area-panel panel="online" title="Pedidos en línea" title-id="pos-online-orders-title"
    eyebrow="WhatsApp + menú digital" description="Confirma únicamente los pedidos que el cliente aseguró contigo."
    icon="bxl-whatsapp" tone="delivery" panel-class="pos-online-orders-panel">
    <x-slot:tools>
        <div class="pos-area-tabs" role="tablist" aria-label="Estado de pedidos en línea">
            <button type="button" wire:click="setTab('pending')" @class(['is-active' => $tab === 'pending'])>Por confirmar <strong>{{ $this->pendingCount }}</strong></button>
            <button type="button" wire:click="setTab('history')" @class(['is-active' => $tab === 'history'])>Historial online</button>
        </div>
        <button type="button" class="pos-btn pos-btn-ghost" wire:click="refreshOrders"
            wire:loading.attr="disabled" wire:target="refreshOrders" aria-label="Actualizar pedidos en línea">
            <i class="bx bx-refresh" aria-hidden="true"></i>
            <span wire:loading.remove wire:target="refreshOrders">Actualizar</span>
            <span wire:loading wire:target="refreshOrders">Actualizando…</span>
        </button>
        <label class="pos-area-search"><i class="bx bx-search"></i><span class="visually-hidden">Buscar pedido online</span>
            <input class="pos-input" type="search" wire:model.live.debounce.400ms="search" placeholder="Folio, cliente o teléfono…">
        </label>
    </x-slot:tools>
    @error('onlineOrder')<div class="pos-alert pos-alert-danger" role="alert">{{ $message }}</div>@enderror
    @forelse($this->orders as $request)
        <article class="pos-online-order" wire:key="online-order-{{ $request->id }}">
            <header><div><span class="pos-online-order__folio">{{ $request->display_folio }}</span><h3>{{ $request->customer_name }}</h3><small><i class="bx bx-store-alt"></i> Menú Digital · {{ $request->customer_phone }} · {{ $request->fulfillment_label }}</small></div><div><strong>${{ number_format((float) $request->total, 2) }}</strong><small>{{ $request->created_at->diffForHumans() }}</small></div></header>
            <div class="pos-online-order__meta"><span><i class="bx bx-wallet"></i>{{ ucfirst($request->payment_method) }}@if($request->cash_tendered) · paga con ${{ number_format((float) $request->cash_tendered, 2) }} · cambio ${{ number_format(max(0, (float)$request->cash_tendered - (float)$request->total), 2) }}@endif</span>@if($request->customer_address)<span><i class="bx bx-map"></i>{{ $request->customer_address }}, {{ $request->customer_neighborhood }}</span>@endif</div>
            <div class="pos-online-order__items">@foreach($request->cart_snapshot as $line)<div><strong>{{ $line['quantity'] }}× {{ $line['product_name'] }}</strong><span>${{ number_format((float)$line['subtotal'], 2) }}</span>@if(!empty($line['addons']) || !empty($line['ingredients']))<small>{{ collect($line['addons'] ?? [])->pluck('name')->merge(collect($line['ingredients'] ?? [])->pluck('name'))->implode(' · ') }}</small>@endif</div>@endforeach</div>
            @if($tab === 'pending')
                <div class="pos-online-order__question"><strong>¿El pedido se concretó o se aseguró?</strong><p>Al confirmar se creará la venta, se enviará a cocina y entrará al flujo de {{ $request->fulfillment === 'delivery' ? 'delivery' : 'ventanilla' }}.</p></div>
                <footer><button type="button" class="pos-btn pos-btn-primary" wire:click="confirmOrder({{ $request->id }})" wire:loading.attr="disabled"><i class="bx bx-check-shield"></i>Sí, concretar e imprimir</button><button type="button" class="pos-btn pos-btn-ghost" wire:click="rejectOrder({{ $request->id }})" wire:confirm="¿Confirmas que este pedido no se concretó?"><i class="bx bx-x"></i>No se concretó</button></footer>
            @else
                <footer><span>{{ $request->status_label }} · {{ $request->display_folio }}</span></footer>
            @endif
        </article>
    @empty
        <div class="pos-area-empty"><span><i class="bx bx-check-circle"></i></span><h3>{{ $tab === 'pending' ? 'Sin pedidos por confirmar' : 'Aún no hay historial' }}</h3><p>Las nuevas solicitudes del menú digital aparecerán aquí.</p></div>
    @endforelse
</x-pos.area-panel>
</div>

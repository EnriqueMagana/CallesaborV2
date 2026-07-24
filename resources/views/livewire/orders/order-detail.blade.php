<div x-data="{ toasts: [] }" x-on:notify.window="
    toasts.push({ id: Date.now(), type: $event.detail.type, message: $event.detail.message });
    setTimeout(() => toasts.shift(), 3500);
">

{{-- ══════════════════════════════════════════════════════════════ Page header --}}
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('app.ordenes') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Volver
        </a>
        <div>
            <h4 class="fw-bold mb-0">
                <i class="bx bx-receipt me-2 text-primary"></i>Orden #{{ $order->id }}
            </h4>
            <span class="badge bg-label-{{ $order->status_color }} mt-1">{{ $order->status_label }}</span>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($order->status !== 'cancelada' && $order->status !== 'pagada')
            @can('cancelar ordenes')
            <button class="btn btn-sm btn-outline-danger" wire:click="openCancelModal">
                <i class="bx bx-x-circle me-1"></i> Cancelar orden
            </button>
            @endcan
        @endif
    </div>
</div>

<div class="row g-4">
    {{-- ══════════════════════════════════════════════════ Left: ticket content --}}
    <div class="col-lg-8">

        {{-- Order header info --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">

                    {{-- ── Tipo de orden ── --}}
                    <div class="col-sm-4">
                        <div class="small text-muted mb-1">Tipo de orden</div>
                        <div class="fw-semibold">
                            <i class="bx {{ $order->type_icon }} me-1"></i>{{ $order->type_label }}
                        </div>
                    </div>

                    {{-- ── Fecha y hora ── --}}
                    <div class="col-sm-4">
                        <div class="small text-muted mb-1">Fecha y hora</div>
                        <div class="fw-semibold">{{ $order->created_at->format('d/m/Y') }}</div>
                        <div class="small text-muted">{{ $order->created_at->format('H:i:s') }}</div>
                    </div>

                    {{-- ── Vendedor / Caja ── --}}
                    <div class="col-sm-4">
                        <div class="small text-muted mb-1">Vendedor</div>
                        <div class="fw-semibold">{{ $order->seller?->name ?? '—' }}</div>
                        <div class="small text-muted">{{ $order->cashRegister?->name ?? '—' }}</div>
                    </div>

                    {{-- ══════════ INFO CONTEXTUAL POR TIPO ══════════ --}}

                    @if($order->type === 'delivery')
                    {{-- ── Delivery: cliente completo ── --}}
                    <div class="col-12">
                        <div class="rounded p-3" style="background:#fff8f0;border:1px solid #ffd199;">
                            <div class="fw-semibold mb-2 text-warning">
                                <i class="bx bx-cycling me-1"></i> Información de entrega
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="small text-muted">Nombre</div>
                                    <div class="fw-semibold">{{ $order->display_name }}</div>
                                </div>
                                @if($order->customer_phone)
                                <div class="col-sm-6">
                                    <div class="small text-muted">Teléfono</div>
                                    <div class="fw-semibold">
                                        <i class="bx bx-phone me-1 text-muted"></i>{{ $order->customer_phone }}
                                    </div>
                                </div>
                                @endif
                                @if($order->customer?->address)
                                <div class="col-sm-8">
                                    <div class="small text-muted">Dirección</div>
                                    <div class="fw-semibold">
                                        <i class="bx bx-map-pin me-1 text-muted"></i>{{ $order->customer->address }}
                                    </div>
                                </div>
                                @endif
                                @if($order->customer?->references)
                                <div class="col-sm-12">
                                    <div class="small text-muted">Referencias</div>
                                    <div class="small">{{ $order->customer->references }}</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @elseif($order->type === 'mesa')
                    {{-- ── Mesa: persona atendida + mesa ── --}}
                    <div class="col-12">
                        <div class="rounded p-3" style="background:#f0f4ff;border:1px solid #b0c4ff;">
                            <div class="fw-semibold mb-2 text-primary">
                                <i class="bx bx-table me-1"></i> Información de mesa
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="small text-muted">Cliente atendido</div>
                                    <div class="fw-semibold">{{ $order->display_name }}</div>
                                    @if($order->customer_phone)
                                        <div class="small text-muted">
                                            <i class="bx bx-phone me-1"></i>{{ $order->customer_phone }}
                                        </div>
                                    @endif
                                </div>
                                @if($order->table_identifier)
                                <div class="col-sm-6">
                                    <div class="small text-muted">Mesa</div>
                                    <div class="fw-semibold">
                                        <i class="bx bx-table me-1 text-muted"></i>{{ $order->table_identifier }}
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @else
                    {{-- ── Ventanilla: solo nombre si existe ── --}}
                    @if($order->display_name !== 'Cliente general')
                    <div class="col-sm-6">
                        <div class="small text-muted mb-1">Cliente</div>
                        <div class="fw-semibold">{{ $order->display_name }}</div>
                        @if($order->customer_phone)
                            <div class="small text-muted">
                                <i class="bx bx-phone me-1"></i>{{ $order->customer_phone }}
                            </div>
                        @endif
                    </div>
                    @endif
                    @endif

                    {{-- ── Notas generales ── --}}
                    @if($order->notes)
                    <div class="col-12">
                        <div class="small text-muted mb-1"><i class="bx bx-note me-1"></i>Notas</div>
                        <div class="small fst-italic">{{ $order->notes }}</div>
                    </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>Ítems del pedido</h6>
            </div>
            <div class="card-body p-0">
                @foreach($order->items as $item)
                <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }} {{ $item->is_cancelled ? 'opacity-50' : '' }}">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        {{-- Product image circle --}}
                        @php $img = $item->product?->image; @endphp
                        @if($img)
                            <img src="{{ asset('storage/'.$img) }}"
                                 style="width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0;margin-top:2px;"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <div style="width:44px;height:44px;border-radius:50%;background:#eaecf0;display:none;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                                <i class="bx bx-dish" style="font-size:1.2rem;color:#8592a3;"></i>
                            </div>
                        @else
                            <div style="width:44px;height:44px;border-radius:50%;background:#eaecf0;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                                <i class="bx bx-dish" style="font-size:1.2rem;color:#8592a3;"></i>
                            </div>
                        @endif
                        <div class="flex-grow-1">
                            {{-- Product name + qty --}}
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-label-secondary" style="font-size:.75rem;">x{{ $item->quantity }}</span>
                                <span class="fw-semibold {{ $item->is_cancelled ? 'text-decoration-line-through text-muted' : '' }}">
                                    {{ $item->product_name }}
                                </span>
                                @if($item->is_cancelled)
                                    <span class="badge bg-label-danger" style="font-size:.65rem;">Cancelado</span>
                                @endif
                            </div>

                            {{-- Addons --}}
                            @if($item->addons->isNotEmpty())
                            <div class="ms-4 mb-1">
                                @foreach($item->addons as $addon)
                                <div class="small text-muted">
                                    <i class="bx bx-plus-circle me-1" style="font-size:.75rem;"></i>
                                    {{ $addon->addon_name }}
                                    @if($addon->quantity > 1) <span class="text-body">x{{ $addon->quantity }}</span> @endif
                                    @if($addon->extra_price > 0)
                                        <span class="text-success ms-1">+${{ number_format($addon->extra_price * $addon->quantity, 2) }}</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Ingredients --}}
                            @if($item->ingredients->isNotEmpty())
                            <div class="ms-4 mb-1">
                                @foreach($item->ingredients as $ing)
                                <div class="small text-muted">
                                    <i class="bx bx-spa me-1" style="font-size:.75rem;"></i>
                                    {{ $ing->ingredient_name }}
                                    @if($ing->quantity > 1) <span class="text-body">x{{ $ing->quantity }}</span> @endif
                                    @if($ing->extra_price > 0)
                                        <span class="text-success ms-1">+${{ number_format($ing->extra_price * $ing->quantity, 2) }}</span>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Notes --}}
                            @if($item->notes)
                                <div class="ms-4 small text-muted fst-italic">{{ $item->notes }}</div>
                            @endif

                            {{-- Cancelled by --}}
                            @if($item->is_cancelled && $item->cancelledBy)
                                <div class="ms-4 small text-danger mt-1">
                                    <i class="bx bx-user-x me-1"></i>
                                    Cancelado por {{ $item->cancelledBy->name }}
                                    {{ $item->cancelled_at?->format('d/m/Y H:i') }}
                                </div>
                            @endif
                        </div>

                        {{-- Price + actions --}}
                        <div class="text-end flex-shrink-0">
                            <div class="fw-semibold">${{ number_format($item->subtotal, 2) }}</div>
                            <div class="small text-muted">${{ number_format($item->product_price, 2) }} c/u</div>
                            @if(!$item->is_cancelled && $order->status !== 'cancelada')
                                <div class="d-flex gap-1 mt-1 justify-content-end">
                                    @can('cancelar ordenes')
                                    <button class="btn btn-sm btn-icon btn-outline-danger"
                                            title="Cancelar ítem"
                                            wire:click="openCancelItemModal({{ $item->id }})">
                                        <i class="bx bx-x"></i>
                                    </button>
                                    @endcan
                                    @can('eliminar items de ordenes')
                                    <button class="btn btn-sm btn-icon btn-outline-danger"
                                            title="Eliminar ítem"
                                            wire:click="confirmDeleteItem({{ $item->id }})">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════════ Right: totals + payments --}}
    <div class="col-lg-4">

        {{-- Totals --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bx bx-calculator me-2 text-primary"></i>Totales</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal</span>
                    <span>${{ number_format($order->subtotal, 2) }}</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold fs-5">
                    <span>Total</span>
                    <span class="text-primary">${{ number_format($order->total, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Payments --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bx bx-credit-card me-2 text-primary"></i>Pagos</h6>
            </div>
            <div class="card-body">
                @if($order->payments->isEmpty())
                    <div class="text-center text-muted small py-2">
                        <i class="bx bx-time me-1"></i> Sin pagos registrados
                    </div>
                @else
                @foreach($order->payments as $payment)
                <div class="d-flex align-items-start justify-content-between mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div>
                        <div class="fw-semibold">
                            <i class="bx {{ $payment->method_icon }} me-1"></i>
                            {{ $payment->method_label }}
                        </div>
                        @if($payment->method === 'efectivo')
                            <div class="small text-muted">Recibido: ${{ number_format($payment->received_amount, 2) }}</div>
                            <div class="small text-success">Cambio: ${{ number_format($payment->change_amount, 2) }}</div>
                        @endif
                    </div>
                    <span class="fw-semibold">${{ number_format($payment->amount, 2) }}</span>
                </div>
                @endforeach

                {{-- Paid total --}}
                @php $totalPaid = $order->payments->sum('amount'); @endphp
                <div class="d-flex justify-content-between pt-2 border-top">
                    <span class="text-muted small">Total pagado</span>
                    <span class="fw-semibold {{ $totalPaid >= $order->total ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($totalPaid, 2) }}
                    </span>
                </div>
                @endif
            </div>
        </div>

        {{-- Cancellation info --}}
        @if($order->status === 'cancelada')
        <div class="card border-danger shadow-sm">
            <div class="card-body">
                <div class="fw-semibold text-danger mb-1">
                    <i class="bx bx-x-circle me-1"></i> Orden cancelada
                </div>
                @if($order->cancellation_reason)
                    <div class="small text-muted mb-1">{{ $order->cancellation_reason }}</div>
                @endif
                @if($order->cancelledBy)
                    <div class="small text-muted">
                        Por: {{ $order->cancelledBy->name }}<br>
                        {{ $order->cancelled_at?->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        </div>
        @endif

    </div>
</div>

{{-- ══════════════════════════════════ MODAL: Cancel item ═════════════════════ --}}
@if($showCancelItemModal)
<div class="modal-backdrop fade show" style="z-index:1110;" wire:click="$set('showCancelItemModal',false)"></div>
<div class="modal fade show d-block" tabindex="-1" style="z-index:1115;" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-x me-2 text-warning"></i>Cancelar ítem</h5>
                <button type="button" class="btn-close" wire:click="$set('showCancelItemModal',false)"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">¿Confirmas que deseas cancelar este ítem? El total de la orden se recalculará.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCancelItemModal',false)">No</button>
                <button type="button" class="btn btn-warning" wire:click="confirmCancelItem">
                    <span wire:loading.remove wire:target="confirmCancelItem"><i class="bx bx-check me-1"></i> Sí, cancelar</span>
                    <span wire:loading wire:target="confirmCancelItem" style="gap:.4rem;"><span class="spinner-border spinner-border-sm"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════ MODAL: Cancel order ════════════════════ --}}
@if($showCancelModal)
<div class="modal-backdrop fade show" style="z-index:1110;" wire:click="$set('showCancelModal',false)"></div>
<div class="modal fade show d-block" tabindex="-1" style="z-index:1115;" role="dialog">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-x-circle me-2 text-danger"></i>Cancelar orden #{{ $order->id }}</h5>
                <button type="button" class="btn-close" wire:click="$set('showCancelModal',false)"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Motivo de cancelación <span class="text-danger">*</span></label>
                <textarea wire:model="cancelReason" class="form-control @error('cancelReason') is-invalid @enderror"
                          rows="3" placeholder="Describe el motivo…"></textarea>
                @error('cancelReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="$set('showCancelModal',false)">Cerrar</button>
                <button type="button" class="btn btn-danger" wire:click="confirmCancel">
                    <span wire:loading.remove wire:target="confirmCancel"><i class="bx bx-x me-1"></i> Cancelar orden</span>
                    <span wire:loading wire:target="confirmCancel" style="gap:.4rem;"><span class="spinner-border spinner-border-sm"></span></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════════ Toasts --}}
<div style="position:fixed;bottom:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;min-width:260px;">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="toast show align-items-center border-0 text-white"
             :class="{
                 'bg-success': toast.type === 'success',
                 'bg-danger':  toast.type === 'danger',
                 'bg-warning': toast.type === 'warning',
                 'bg-info':    toast.type === 'info'
             }"
             role="alert">
            <div class="d-flex">
                <div class="toast-body" x-text="toast.message"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" @click="toasts = toasts.filter(t => t.id !== toast.id)"></button>
            </div>
        </div>
    </template>
</div>

</div>

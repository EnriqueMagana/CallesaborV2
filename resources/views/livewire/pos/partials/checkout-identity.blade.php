<div class="co-section-title"><i class="bx bx-user-check me-1"></i>¿Para quién es el pedido?</div>

@can('aplicar descuentos')
    <div class="pos-checkout-identity-switch" role="tablist" aria-label="Tipo de persona a buscar">
        <button type="button" role="tab" wire:click="setCheckoutIdentityType('customer')"
            aria-selected="{{ $checkoutIdentityType === 'customer' ? 'true' : 'false' }}"
            class="{{ $checkoutIdentityType === 'customer' ? 'is-active' : '' }}">
            <i class="bx bx-user" aria-hidden="true"></i>
            <span><strong>Cliente</strong><small>Buscar en clientes</small></span>
        </button>
        <button type="button" role="tab" wire:click="setCheckoutIdentityType('employee')"
            aria-selected="{{ $checkoutIdentityType === 'employee' ? 'true' : 'false' }}"
            class="{{ $checkoutIdentityType === 'employee' ? 'is-active' : '' }}">
            <i class="bx bx-id-card" aria-hidden="true"></i>
            <span><strong>Empleado</strong><small>Aplicar beneficio</small></span>
        </button>
    </div>
@endcan

<div class="pos-checkout-identity">
    @if($checkoutIdentityType === 'customer' && $customerId)
        <div class="pos-checkout-identity__selected">
            <span class="pos-discount-beneficiary__avatar" aria-hidden="true">{{ strtoupper(substr($customerName, 0, 1)) }}</span>
            <span class="pos-discount-beneficiary__identity">
                <span class="pos-checkout-identity__badge">Cliente</span>
                <strong>{{ $customerName }}</strong>
                <small>{{ $customerPhone ?: 'Sin teléfono registrado' }}</small>
            </span>
            <button type="button" wire:click="clearCustomer" aria-label="Cambiar cliente">
                <i class="bx bx-x" aria-hidden="true"></i><span>Cambiar</span>
            </button>
        </div>
    @elseif($checkoutIdentityType === 'employee' && $this->selectedDiscountEmployee)
        <div class="pos-checkout-identity__selected">
            <span class="pos-discount-beneficiary__avatar" aria-hidden="true">{{ strtoupper(substr($this->selectedDiscountEmployee->name, 0, 1)) }}</span>
            <span class="pos-discount-beneficiary__identity">
                <span class="pos-checkout-identity__badge is-employee">Empleado</span>
                <strong>{{ $this->selectedDiscountEmployee->name }}</strong>
                <small>{{ $this->selectedDiscountEmployee->email }}</small>
            </span>
            <button type="button" wire:click="clearDiscountEmployee" aria-label="Cambiar empleado beneficiario">
                <i class="bx bx-x" aria-hidden="true"></i><span>Cambiar</span>
            </button>
        </div>

        @if($this->employeeDiscountTotal > 0)
            <div class="pos-discount-beneficiary__feedback is-applied" role="status" aria-live="polite">
                <i class="bx bx-check-circle" aria-hidden="true"></i>
                Beneficio aplicado: <strong>−${{ number_format($this->employeeDiscountTotal, 2) }}</strong>
                <span>· Total actualizado: ${{ number_format($this->cartTotal, 2) }}</span>
            </div>
        @else
            <div class="pos-discount-beneficiary__feedback" role="status" aria-live="polite">
                <i class="bx bx-info-circle" aria-hidden="true"></i>
                No existe un descuento compatible con los productos, canal o vigencia actual.
            </div>
        @endif
    @else
        <label class="co-label" for="checkout-identity-search">
            {{ $checkoutIdentityType === 'employee' ? 'Buscar empleado' : 'Buscar cliente' }}
        </label>
        <div class="co-search-group">
            <div class="co-search-wrap">
                <i class="bx bx-search co-search-icon" aria-hidden="true"></i>
                <input id="checkout-identity-search" type="search"
                    wire:model.live.debounce.400ms="customerSearch"
                    class="co-input co-search-input"
                    placeholder="{{ $checkoutIdentityType === 'employee' ? 'Nombre, correo o teléfono del empleado…' : 'Nombre, teléfono o correo del cliente…' }}"
                    autocomplete="off">
            </div>
            @if($checkoutIdentityType === 'customer')
                <button type="button" wire:click="openAddCustomerModal" class="pos-btn pos-btn-primary co-search-btn">
                    <i class="bx bx-user-plus" aria-hidden="true"></i><span>Nuevo</span>
                </button>
            @endif
        </div>

        @if(strlen(trim($customerSearch)) >= 2)
            <div class="pos-checkout-identity-results" role="listbox"
                aria-label="{{ $checkoutIdentityType === 'employee' ? 'Empleados encontrados' : 'Clientes encontrados' }}">
                @forelse($this->checkoutIdentitySearchResults as $person)
                    <button type="button" role="option"
                        wire:key="checkout-identity-{{ $checkoutIdentityType }}-{{ $person->id }}"
                        wire:click="selectCheckoutIdentity({{ $person->id }})">
                        <span class="pos-discount-beneficiary__avatar" aria-hidden="true">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
                        <span>
                            <strong>{{ $person->name }}</strong>
                            <small>{{ $checkoutIdentityType === 'employee' ? ($person->email ?: $person->phone) : ($person->phone ?: $person->email) }}</small>
                        </span>
                        <span class="pos-checkout-identity__result-type">{{ $checkoutIdentityType === 'employee' ? 'Empleado' : 'Cliente' }}</span>
                    </button>
                @empty
                    <p><i class="bx bx-user-x" aria-hidden="true"></i>No encontramos coincidencias en {{ $checkoutIdentityType === 'employee' ? 'el personal activo' : 'clientes' }}.</p>
                @endforelse
            </div>
        @endif

        @if($checkoutIdentityType === 'customer')
            <div class="co-grid">
                <div>
                    <label class="co-label">Nombre</label>
                    <input type="text" wire:model="customerName" class="co-input" placeholder="Nombre del cliente">
                </div>
                <div>
                    <label class="co-label">Teléfono</label>
                    <input type="tel" wire:model="customerPhone" class="co-input" placeholder="+52 999…">
                </div>
            </div>
        @endif
    @endif
</div>

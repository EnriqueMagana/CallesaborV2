<div class="pos-header">
    <div class="pos-header-left">
        <div class="pos-logo">
            <img src="{{ asset('assets/img/favicon/favicon.ico') }}" alt=""
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <i class="bx bx-restaurant" style="display:none;font-size:1.2rem"></i>
        </div>
        <div class="pos-header-info">
            <span class="brand-name">{{ config('app.name') }}</span>
            <span class="brand-sub">Punto de Venta</span>
        </div>
    </div>
    <div class="pos-header-right">
        <a href="{{ route('app.dashboard') }}" class="btn-header-action">
            <i class="bx bx-home-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('app.caja') }}" class="btn-header-action">
            <i class="bx bx-calculator"></i>
            <span>Caja</span>
        </a>
        <div style="display:flex;align-items:center;gap:6px;background:rgba(113,221,55,.1);border:1px solid rgba(113,221,55,.3);border-radius:8px;padding:5px 10px;font-size:.78rem;font-weight:700;color:#2d9a1e">
            <i class="bx bx-lock-open-alt"></i>
            <span>{{ $this->activeCashRegister->name }}</span>
        </div>
        <button class="btn-cart-toggle" @click="showCart = !showCart" :class="showCart ? 'active' : ''">
            <i class="bx bx-cart"></i>
            @if(!empty($cart))
                <span class="cart-count">{{ $this->cartCount }}</span>
            @endif
            <span>Carrito</span>
        </button>
    </div>
</div>

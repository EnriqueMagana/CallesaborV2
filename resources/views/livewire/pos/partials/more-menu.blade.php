<div class="pos-more-layer" x-cloak>
    <button type="button" class="pos-more-backdrop" tabindex="-1"
        x-show="showMore"
        x-transition:enter="pos-backdrop-enter"
        x-transition:enter-start="pos-backdrop-enter-start"
        x-transition:enter-end="pos-backdrop-enter-end"
        x-transition:leave="pos-backdrop-leave"
        x-transition:leave-start="pos-backdrop-leave-start"
        x-transition:leave-end="pos-backdrop-leave-end"
        @click="closeMore()" aria-label="Cerrar más opciones"></button>

    <section id="pos-more-menu" class="pos-more-sheet" role="dialog" aria-modal="true"
        aria-labelledby="pos-more-title" aria-describedby="pos-more-description"
        x-show="showMore"
        x-transition:enter="pos-sheet-enter"
        x-transition:enter-start="pos-sheet-enter-start"
        x-transition:enter-end="pos-sheet-enter-end"
        x-transition:leave="pos-sheet-leave"
        x-transition:leave-start="pos-sheet-leave-start"
        x-transition:leave-end="pos-sheet-leave-end"
        @keydown.tab="trapFocus($event, $el)">
        <span class="pos-more-sheet__handle" aria-hidden="true"></span>
        <header class="pos-more-sheet__header">
            <div>
                <h2 id="pos-more-title">Más opciones</h2>
                <p id="pos-more-description">Herramientas del punto de venta</p>
            </div>
            <button type="button" class="pos-more-sheet__close" x-ref="moreClose"
                @click="closeMore()" aria-label="Cerrar más opciones">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="pos-more-grid">
            @can('gestionar borradores en punto de venta')
                <button type="button" class="pos-more-action" data-tone="violet"
                    @click="closeMore(false); showSaved = true; $wire.openSavedOrdersModal()"
                    wire:loading.attr="disabled" wire:target="openSavedOrdersModal">
                    <span class="pos-more-action__icon"><i class="bx bx-bookmark" aria-hidden="true"></i></span>
                    <strong>Guardados</strong><small>Retomar borradores</small>
                </button>
            @endcan

            @can('reimprimir tickets')
                <button type="button" class="pos-more-action" data-tone="blue"
                    @click="closeMore(false); showOnlyPanel('reprint'); $wire.openReprintPanel()"
                    data-pos-panel="reprint">
                    <span class="pos-more-action__icon"><i class="bx bx-printer" aria-hidden="true"></i></span>
                    <strong>Reimprimir</strong><small>Cocina y cliente</small>
                </button>
            @endcan

            @canany(['registrar gastos', 'registrar movimientos de caja'])
                <button type="button" class="pos-more-action" data-tone="rose"
                    @click="closeMore(false)"
                    wire:click="openOperationsModal('expense')"
                    wire:loading.attr="disabled" wire:target="openOperationsModal"
                    data-pos-operations>
                    <span class="pos-more-action__icon"><i class="bx bx-trending-down" aria-hidden="true"></i></span>
                    <strong>Registrar gasto</strong><small>Salida de caja</small>
                </button>
            @endcanany

            @can('registrar movimientos de caja')
                <button type="button" class="pos-more-action" data-tone="green"
                    @click="closeMore(false)"
                    wire:click="openOperationsModal('income')"
                    wire:loading.attr="disabled" wire:target="openOperationsModal">
                    <span class="pos-more-action__icon"><i class="bx bx-trending-up" aria-hidden="true"></i></span>
                    <strong>Ingreso de caja</strong><small>Agregar efectivo</small>
                </button>
            @endcan

            @canany(['registrar salida de insumos', 'ajustar inventario'])
                <button type="button" class="pos-more-action" data-tone="orange"
                    @click="closeMore(false)"
                    wire:click="openOperationsModal('inventory_out')"
                    wire:loading.attr="disabled" wire:target="openOperationsModal">
                    <span class="pos-more-action__icon"><i class="bx bx-package" aria-hidden="true"></i></span>
                    <strong>Salida de insumos</strong><small>Descontar existencia</small>
                </button>
            @endcanany

            @can('editar datos de ordenes en punto de venta')
                <button type="button" class="pos-more-action" data-tone="blue"
                    @click="closeMore(false)" wire:click="openOrderDataModal"
                    wire:loading.attr="disabled" wire:target="openOrderDataModal">
                    <span class="pos-more-action__icon"><i class="bx bx-edit-alt" aria-hidden="true"></i></span>
                    <strong>Cambiar datos</strong><small>Direcci&oacute;n, contacto o pago</small>
                </button>
            @endcan

            @canany(['solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes'])
                <a href="{{ route('app.ordenes', ['action' => 'request']) }}" class="pos-more-action" data-tone="blue">
                    <span class="pos-more-action__icon"><i class="bx bx-message-square-edit" aria-hidden="true"></i></span>
                    <strong>Solicitar ajuste</strong><small>Productos o cancelaci&oacute;n</small>
                </a>
            @endcanany

            @if (app(\App\Services\DeliveryModulePolicy::class)->enabled())
                @can('reasignar pedidos delivery')
                    <button type="button" class="pos-more-action" data-tone="green"
                        @click="closeMore(false)" wire:click="openDeliveryDispatchModal"
                        wire:loading.attr="disabled" wire:target="openDeliveryDispatchModal">
                        <span class="pos-more-action__icon"><i class="bx bx-group" aria-hidden="true"></i></span>
                        <strong>Repartidores</strong><small>Ver y reasignar pedidos</small>
                    </button>
                @endcan
            @endif

            <a href="{{ route('app.dashboard') }}" class="pos-more-action" data-tone="neutral">
                <span class="pos-more-action__icon"><i class="bx bx-home-alt" aria-hidden="true"></i></span>
                <strong>Inicio</strong><small>Volver al dashboard</small>
            </a>
        </div>
    </section>
</div>

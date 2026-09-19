{{--
    Tabla de un área del corte: todos los pedidos del turno de esa área y, al
    pie, lo que debe haber. Los pedidos cancelados o sin cobrar se listan pero
    no suman (quedan atenuados y con la razón).
--}}
@php($totals = $area['totals'])
<section class="cash-cut-area-table" aria-labelledby="cash-cut-area-{{ $key }}">
    <header>
        <span class="cash-cut-area-table__mark is-{{ $key }}" aria-hidden="true"><i class="bx {{ $area['icon'] }}"></i></span>
        <div>
            <h3 id="cash-cut-area-{{ $key }}">{{ $area['label'] }}</h3>
            <p>{{ $totals['listed'] }} {{ $totals['listed'] === 1 ? 'pedido' : 'pedidos' }} · {{ $totals['counted'] }} {{ $totals['counted'] === 1 ? 'suma' : 'suman' }} al corte</p>
        </div>
        <div class="cash-cut-area-table__due">
            <small>Debe haber</small>
            <strong>${{ number_format($totals['total'], 2) }}</strong>
        </div>
    </header>

    @if ($area['rows'] === [])
        <p class="cash-cut-area-table__empty">Sin pedidos de {{ mb_strtolower($area['label']) }} en este turno.</p>
    @else
        <div class="table-responsive">
            <table class="table app-table cash-cut-table cash-cut-area-table__table">
                <caption class="visually-hidden">Pedidos de {{ $area['label'] }} y lo que debe haber por método</caption>
                <thead>
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end cash-column">Efectivo</th>
                        <th scope="col" class="text-end">Tarjeta</th>
                        <th scope="col" class="text-end">Transferencia</th>
                        <th scope="col" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($area['rows'] as $row)
                        <tr class="{{ $row['counted'] ? '' : 'is-excluded' }}" wire:key="cut-{{ $key }}-{{ $row['id'] }}">
                            <td>
                                <strong>{{ $row['folio'] }}</strong>
                                <small>{{ \App\Support\BusinessTime::format($row['created_at'], 'g:i A') }} · {{ $row['type_label'] }}</small>
                            </td>
                            <td>{{ $row['who'] }}</td>
                            <td>
                                <span class="cash-cut-area-table__status is-{{ $row['status'] }}">{{ $row['status_label'] }}</span>
                                @if ($row['note'])<small>{{ $row['note'] }}</small>@endif
                            </td>
                            <td class="text-end cash-column">${{ number_format($row['efectivo'], 2) }}</td>
                            <td class="text-end app-muted">${{ number_format($row['tarjeta'], 2) }}</td>
                            <td class="text-end app-muted">${{ number_format($row['transfer'], 2) }}</td>
                            <td class="text-end app-money">
                                ${{ number_format($row['total'], 2) }}
                                @if ($row['refunded'] > 0.009)<small>Reembolso -${{ number_format($row['refunded'], 2) }}</small>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3">Debe haber en {{ $area['label'] }}</th>
                        <th class="text-end cash-column">${{ number_format($totals['efectivo'], 2) }}</th>
                        <th class="text-end">${{ number_format($totals['tarjeta'], 2) }}</th>
                        <th class="text-end">${{ number_format($totals['transfer'], 2) }}</th>
                        <th class="text-end">${{ number_format($totals['total'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</section>

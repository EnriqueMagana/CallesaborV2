<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\CashRegisterCut;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaService;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketTemplate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ThermalTicketRenderer
{
    public function renderOrder(
        Order $order,
        string $type,
        ?string $printArea = null,
        bool $autoPrint = true,
    ): string {
        $order->loadMissing(['items.addons', 'items.ingredients', 'items.product.category.printArea', 'seller', 'payments', 'customer', 'mesa.area']);

        // La cocina nunca debe preparar partidas retiradas. En los tickets del
        // cliente sí se conservan como evidencia, marcadas y fuera del total.
        $items = $type === 'kitchen_area'
            ? $order->items->filter(fn ($item) => ! (bool) $item->is_cancelled)
            : $order->items;

        if ($type === 'kitchen_area') {
            $items = $this->kitchenLines($items);
        }

        if ($type === 'kitchen_area' && $printArea === null) {
            $areas = $items->groupBy(fn (array $item) => (string) ($item['print_area_name'] ?? 'General'));
            if ($areas->count() > 1) {
                return $this->renderBatch('kitchen_area', $areas->map(
                    fn ($areaItems, $areaName) => array_merge(
                        $this->orderPayload($order, $type, (string) $areaName, $areaItems),
                        ['auto_print' => $autoPrint],
                    )
                )->values()->all(), $autoPrint);
            }

            $resolvedArea = (string) ($areas->keys()->first() ?? 'General');
            if (strcasecmp($resolvedArea, 'General') !== 0) {
                $printArea = $resolvedArea;
            }
        }

        if ($type === 'kitchen_area' && $printArea) {
            $items = $items->filter(
                fn (array $item) => strcasecmp((string) ($item['print_area_name'] ?? 'General'), $printArea) === 0
            );
        }

        return $this->render($type, array_merge(
            $this->orderPayload($order, $type, $printArea, $items),
            ['auto_print' => $autoPrint],
        ));
    }

    private function orderPayload(Order $order, string $type, ?string $printArea, $items): array
    {
        return [
            'title' => match ($type) {
                'delivery' => 'DELIVERY',
                'kitchen_area' => $printArea ? strtoupper($printArea) : 'COMANDA',
                'customer' => 'CUENTA DEL CLIENTE',
                default => 'RECIBO DE VENTA',
            },
            'folio' => $order->display_folio,
            'date' => $this->businessDate($order->created_at) ?? $this->businessNow(),
            'customer' => $order->display_name,
            'served_by' => $order->seller?->name,
            'table' => $order->table_identifier ?: $order->mesa?->display_name,
            'area' => $type === 'kitchen_area'
                ? ($printArea ?: $order->mesa?->area?->name ?: 'General')
                : ($order->mesa?->area?->name ?: $printArea),
            'notes' => $order->notes,
            'items' => collect($items)->map(fn ($item) => is_array($item) ? $item : [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'is_cancelled' => (bool) $item->is_cancelled,
                'notes' => $item->notes,
                'modifiers' => $item->addons->map(fn ($addon) => [
                    'name' => '+ '.$addon->addon_name,
                    'price' => (float) $addon->extra_price * max(1, (int) $addon->quantity),
                ])->concat($item->ingredients->map(fn ($ingredient) => [
                    'name' => '• '.$ingredient->ingredient_name.($ingredient->quantity > 1 ? ' x'.$ingredient->quantity : ''),
                    'price' => (float) $ingredient->extra_price * max(1, (int) $ingredient->quantity),
                ]))->concat(collect($item->promotion_selections ?? [])->flatMap(
                    fn (array $group) => collect($group['items'] ?? [])->map(fn (array $selected) => [
                        'name' => '• '.($selected['product_name'] ?? 'Producto').((int) ($selected['quantity'] ?? 1) > 1 ? ' x'.(int) $selected['quantity'] : ''),
                        'price' => 0,
                    ])
                ))->when((float) ($item->promotion_discount ?? 0) > 0, fn ($modifiers) => $modifiers->push([
                    'name' => '• Oferta: '.data_get($item->promotion_rule_snapshot, 'label', 'promoción automática')
                        .' (-$'.number_format((float) $item->promotion_discount, 2).')',
                    'price' => 0,
                ]))->when($type !== 'kitchen_area' && (float) ($item->discount_amount ?? 0) > 0, fn ($modifiers) => $modifiers->push([
                    'name' => '• Descuento: '.data_get($item->discount_snapshot, 'name', 'descuento automático')
                        .' (-$'.number_format((float) $item->discount_amount, 2).')',
                    'price' => 0,
                ]))->values()->all(),
            ])->values()->all(),
            'total' => (float) $order->total,
            'payments' => $order->payments->map(fn ($payment) => [
                'label' => $payment->method_label,
                'amount' => (float) $payment->amount,
                'change' => (float) ($payment->change_amount ?? 0),
            ])->values()->all(),
            'delivery' => [
                'phone' => $order->customer_phone ?: $order->customer?->phone,
                'address' => $order->customer_address ?: $order->customer?->address,
                'references' => $order->customer_references ?: $order->customer?->references,
                'method' => $order->delivery_method_label,
            ],
            'tracking_url' => route('kiosk.track', $order->ensurePublicToken()),
        ];
    }

    /**
     * Expand configurable promotions into the real products selected by the
     * cashier. Kitchen routing must follow each product's print area instead
     * of the promotion container, whose product_id is intentionally null.
     */
    private function kitchenLines($items)
    {
        $selectedProductIds = collect($items)
            ->flatMap(fn ($item) => collect($item->promotion_selections ?? [])
                ->flatMap(fn (array $group) => collect($group['items'] ?? [])->pluck('product_id')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $selectedProducts = Product::query()
            ->with('category.printArea')
            ->whereIn('id', $selectedProductIds)
            ->get()
            ->keyBy('id');

        return collect($items)->flatMap(function ($item) use ($selectedProducts) {
            $selections = collect($item->promotion_selections ?? [])
                ->flatMap(fn (array $group) => collect($group['items'] ?? [])->map(
                    fn (array $selected) => array_merge($selected, [
                        'group_name' => $group['group_name'] ?? null,
                    ])
                ));

            if ($selections->isNotEmpty()) {
                return $selections->map(function (array $selected) use ($item, $selectedProducts) {
                    $product = $selectedProducts->get((int) ($selected['product_id'] ?? 0));
                    $category = $product?->category;
                    $printArea = $category?->printArea;

                    return [
                        'name' => $product?->name ?? ($selected['product_name'] ?? 'Producto'),
                        'quantity' => max(1, (int) $item->quantity) * max(1, (int) ($selected['quantity'] ?? 1)),
                        'subtotal' => 0,
                        'notes' => $item->notes,
                        'modifiers' => [],
                        'print_area_name' => $printArea?->name
                            ?? $selected['print_area_name']
                            ?? $category?->name
                            ?? $selected['category_name']
                            ?? $selected['group_name']
                            ?? 'General',
                    ];
                });
            }

            return [[
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'notes' => $item->notes,
                'modifiers' => $item->addons->map(fn ($addon) => [
                    'name' => '+ '.$addon->addon_name,
                    'price' => (float) $addon->extra_price * max(1, (int) $addon->quantity),
                ])->concat($item->ingredients->map(fn ($ingredient) => [
                    'name' => '• '.$ingredient->ingredient_name.($ingredient->quantity > 1 ? ' x'.$ingredient->quantity : ''),
                    'price' => (float) $ingredient->extra_price * max(1, (int) $ingredient->quantity),
                ]))->when((float) ($item->promotion_discount ?? 0) > 0, fn ($modifiers) => $modifiers->push([
                    'name' => '• Oferta: '.data_get($item->promotion_rule_snapshot, 'label', 'promoción automática'),
                    'price' => 0,
                ]))->when((float) ($item->discount_amount ?? 0) > 0, fn ($modifiers) => $modifiers->push([
                    'name' => '• Descuento: '.data_get($item->discount_snapshot, 'name', 'descuento automático'),
                    'price' => 0,
                ]))->values()->all(),
                'print_area_name' => $item->product?->category?->printArea?->name
                    ?? $item->product?->category?->name
                    ?? 'General',
            ]];
        })->values();
    }

    public function renderCashCut(CashRegisterCut $cut): string
    {
        $cut->loadMissing(['cashRegister', 'generator']);

        return $this->render('cash_cut', [
            'title' => 'CORTE DE CAJA',
            'cut' => $this->cashCutPayload($cut),
            'total' => (float) $cut->expected_cash,
            'tracking_url' => null,
        ]);
    }

    public function renderInventoryPurchase(InventoryPurchase $purchase, bool $autoPrint = false): string
    {
        $purchase->loadMissing(['items.inventoryItem', 'requester', 'receiver']);

        return $this->render('inventory_purchase', [
            'title' => 'LISTA DE COMPRA DE INSUMOS',
            'folio' => $purchase->folio,
            'date' => $this->businessDate($purchase->issued_at),
            'requested_by' => $purchase->requester?->name ?: 'Sin asignar',
            'received_by' => $purchase->receiver?->name,
            'received_at' => $this->businessDate($purchase->received_at),
            'status' => $purchase->status,
            'notes' => $purchase->notes,
            'items' => $purchase->items->map(fn ($line) => [
                'name' => $line->item_name,
                'quantity' => (float) $line->requested_quantity,
                'unit' => InventoryItem::UNITS[$line->unit]['short'] ?? $line->unit,
                'notes' => $line->notes,
                'received_quantity' => $line->received_quantity,
            ])->all(),
            'tracking_url' => null,
            'auto_print' => $autoPrint,
        ]);
    }

    public function renderMesaAccount(
        Mesa $mesa,
        string $accountLabel,
        array $items,
        float $total,
        array $payments,
        ?MesaAssignment $assignment,
        string $cashierName,
        bool $autoPrint = true,
        ?string $trackingUrl = null,
    ): string {
        $mesa->loadMissing('area');

        $paymentPayload = $this->mesaPaymentsPayload($payments);

        return $this->render('customer', [
            'title' => 'TICKET DE MESA',
            'folio' => $accountLabel,
            'date' => $this->businessNow(),
            'table' => $mesa->display_name,
            'area' => $mesa->area?->name,
            'served_by' => $assignment?->waiter?->name,
            'cashier' => $cashierName,
            'items' => collect($items)->map(fn ($item) => [
                'name' => $item['name'] ?? 'Producto',
                'quantity' => $item['qty'] ?? $item['quantity'] ?? 1,
                'subtotal' => (float) ($item['subtotal'] ?? 0),
                'modifiers' => [],
                'notes' => null,
            ])->all(),
            'payments' => $paymentPayload,
            'paid_total' => (float) collect($paymentPayload)->sum('amount'),
            'balance' => max(0, $total - (float) collect($paymentPayload)->sum('amount')),
            'total' => $total,
            'tracking_url' => $trackingUrl,
            'auto_print' => $autoPrint,
        ]);
    }

    public function renderMesaService(MesaService $service): string
    {
        $service->loadMissing([
            'mesas.area',
            'primaryMesa.area',
            'closer',
            'orders.items',
            'orders.payments',
            'splits',
        ]);

        $areas = $service->mesas->pluck('area.name')->filter()->unique()->implode(', ');
        $members = $service->mesas
            ->map(fn ($mesa) => $mesa->pivot->mesa_label_snapshot ?: $mesa->display_name)
            ->implode(', ');
        $splitLabels = $service->splits
            ->flatMap(fn ($split) => collect($split->split_data ?? [])
                ->filter(fn ($account) => (bool) ($account['paid'] ?? false))
                ->pluck('label'))
            ->filter()
            ->unique()
            ->implode(', ');

        return $this->render('customer', [
            'title' => $service->status === 'pagada' ? 'HISTÓRICO DE MESA' : 'AUDITORÍA DE MESA',
            'folio' => $service->service_label,
            'date' => $this->businessDate($service->closed_at ?? $service->updated_at),
            'table' => $service->service_label,
            'area' => $areas ?: $service->primaryMesa?->area?->name,
            'served_by' => $service->opener_name_snapshot ?: 'Sin asignar',
            'cashier' => $service->closer?->name,
            'items' => $service->orders->flatMap(fn ($order) => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'is_cancelled' => (bool) $item->is_cancelled,
                'modifiers' => [],
                'notes' => null,
            ]))->all(),
            'payments' => $this->mesaPaymentsPayload($service->orders->flatMap->payments),
            'paid_total' => (float) $service->orders->flatMap->payments->sum('amount'),
            'balance' => max(0, (float) $service->total_snapshot - (float) $service->orders->flatMap->payments->sum('amount')),
            'total' => (float) $service->total_snapshot,
            'notes' => collect([
                $members ? "Mesas ocupadas: {$members}" : null,
                $splitLabels ? "Subcuentas cobradas: {$splitLabels}" : null,
                $service->status === 'liberada' ? "Liberada sin cobro: {$service->close_reason}" : null,
                'Apertura: '.$this->businessDate($service->opened_at),
            ])->filter()->implode(' · '),
            'tracking_url' => $this->trackingUrlForOrders($service->orders),
        ]);
    }

    private function mesaPaymentsPayload($payments): array
    {
        return collect($payments)
            ->map(function ($payment): array {
                $method = (string) data_get($payment, 'method', '');
                $canonicalMethod = match ($method) {
                    'cash', 'efectivo' => 'cash',
                    'card', 'tarjeta' => 'card',
                    'transfer', 'transferencia' => 'transfer',
                    'contra_entrega' => 'contra_entrega',
                    default => $method ?: 'other',
                };

                return [
                    'method' => $canonicalMethod,
                    'label' => match ($canonicalMethod) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                        'contra_entrega' => 'Contra entrega',
                        default => ucfirst($method ?: 'Pago'),
                    },
                    'amount' => (float) data_get($payment, 'amount', 0),
                    'change' => (float) (data_get($payment, 'cash_change') ?? data_get($payment, 'change_amount', 0)),
                    'card_last4' => data_get($payment, 'card_last4'),
                    'reference' => data_get($payment, 'transfer_ref') ?? data_get($payment, 'transfer_reference'),
                ];
            })
            ->groupBy('method')
            ->map(fn ($group) => [
                'label' => $group->first()['label'],
                'amount' => (float) $group->sum('amount'),
                'change' => (float) $group->sum('change'),
                'card_last4' => $group->pluck('card_last4')->filter()->unique()->implode(', '),
                'reference' => $group->pluck('reference')->filter()->unique()->implode(', '),
            ])
            ->values()
            ->all();
    }

    private function trackingUrlForOrders($orders): ?string
    {
        $order = collect($orders)->first();

        return $order instanceof Order
            ? route('kiosk.track', $order->ensurePublicToken())
            : null;
    }

    public function renderPreview(string $type, ?TicketTemplate $template = null, ?BusinessSetting $business = null): string
    {
        if ($type === 'cash_cut') {
            return $this->render($type, [
                'title' => 'CORTE DE CAJA',
                'cut' => $this->cashCutPreviewPayload(),
                'total' => 3245.50,
                'tracking_url' => null,
                'preview' => true,
            ], $template, $business);
        }

        if ($type === 'inventory_purchase') {
            return $this->render($type, [
                'title' => 'LISTA DE COMPRA DE INSUMOS',
                'folio' => 'CMP-2607-000018',
                'date' => $this->businessNow(),
                'requested_by' => 'Usuario de almacén',
                'status' => 'pending',
                'notes' => 'Comprar con el proveedor habitual.',
                'items' => [
                    ['name' => 'Harina de trigo', 'quantity' => 10, 'unit' => 'kg', 'notes' => 'Costal cerrado'],
                    ['name' => 'Aceite vegetal', 'quantity' => 6, 'unit' => 'L', 'notes' => null],
                ],
                'tracking_url' => null,
                'preview' => true,
                'auto_print' => false,
            ], $template, $business);
        }

        return $this->render($type, [
            'title' => TicketTemplate::TYPES[$type]['name'] ?? 'Ticket',
            'folio' => '001',
            'date' => $this->businessNow(),
            'customer' => 'Cliente de ejemplo',
            'served_by' => 'Usuario de caja',
            'cashier' => 'Usuario de caja',
            'table' => in_array($type, ['customer', 'kitchen_area'], true) ? 'Mesa 4' : null,
            'area' => $type === 'kitchen_area' ? 'Cocina' : 'Salón',
            'notes' => $type === 'kitchen_area' ? 'Entregar todos los platillos juntos.' : 'Sin cebolla',
            'items' => [
                ['name' => 'Hamburguesa especial', 'quantity' => 1, 'subtotal' => 145, 'modifiers' => [['name' => '+ Queso extra', 'price' => 15]], 'notes' => $type === 'kitchen_area' ? 'Sin cebolla; término medio.' : null],
                ['name' => 'Agua fresca', 'quantity' => 2, 'subtotal' => 70, 'modifiers' => [], 'notes' => null],
            ],
            'payments' => [['label' => 'Efectivo', 'amount' => 215, 'change' => 0]],
            'delivery' => ['phone' => '5555555555', 'address' => 'Av. Principal 123', 'references' => 'Portón negro', 'method' => 'Contra entrega'],
            'total' => 215,
            'tracking_url' => 'https://example.test/pedido/demo',
            'preview' => true,
        ], $template, $business);
    }

    private function cashCutPayload(CashRegisterCut $cut): array
    {
        $channels = [
            ['label' => 'Ventanilla', 'cash' => (float) $cut->v_efectivo, 'card' => (float) $cut->v_tarjeta, 'transfer' => (float) $cut->v_transfer],
            ['label' => 'Mesas', 'cash' => (float) $cut->m_efectivo, 'card' => (float) $cut->m_tarjeta, 'transfer' => (float) $cut->m_transfer],
            ['label' => 'Delivery', 'cash' => (float) $cut->d_efectivo, 'card' => (float) $cut->d_tarjeta, 'transfer' => (float) $cut->d_transfer],
        ];

        return [
            'folio' => $cut->folio,
            'register' => $cut->cashRegister?->name ?? 'Caja',
            'opened_at' => $this->businessDate($cut->cashRegister?->opened_at, 'd/m/Y h:i A'),
            'closed_at' => $this->businessDate($cut->generated_at, 'd/m/Y h:i A'),
            'cashier' => $cut->generator?->name ?? 'Sin asignar',
            'channels' => collect($channels)->map(fn (array $channel) => array_merge($channel, [
                'total' => $channel['cash'] + $channel['card'] + $channel['transfer'],
            ]))->all(),
            'payment_methods' => [
                ['label' => 'Efectivo', 'amount' => collect($channels)->sum('cash')],
                ['label' => 'Tarjeta', 'amount' => collect($channels)->sum('card')],
                ['label' => 'Transferencia', 'amount' => collect($channels)->sum('transfer')],
            ],
            'sales_total' => collect($channels)->sum(fn (array $channel) => $channel['cash'] + $channel['card'] + $channel['transfer']),
            'initial_amount' => (float) $cut->initial_amount,
            'cash_sales' => (float) $cut->total_cash_in,
            'cash_incomes' => (float) ($cut->total_cash_income ?? 0),
            'cash_expenses' => (float) $cut->total_expenses_cash,
            'expected_cash' => (float) $cut->expected_cash,
            'declared_cash' => (float) $cut->declared_cash,
            'difference' => (float) $cut->difference,
            'notes' => $cut->cashRegister?->closing_notes,
            'generated_at' => $this->businessDate($cut->generated_at, 'd/m/Y h:i A'),
        ];
    }

    private function cashCutPreviewPayload(): array
    {
        $channels = [
            ['label' => 'Ventanilla', 'cash' => 1280.00, 'card' => 620.00, 'transfer' => 180.00, 'total' => 2080.00],
            ['label' => 'Mesas', 'cash' => 760.00, 'card' => 350.00, 'transfer' => 55.50, 'total' => 1165.50],
            ['label' => 'Delivery', 'cash' => 0.00, 'card' => 0.00, 'transfer' => 0.00, 'total' => 0.00],
        ];

        return [
            'folio' => 'COR-00018', 'register' => 'Caja principal',
            'opened_at' => now($this->businessTimezone())->subHours(8)->format('d/m/Y h:i A'), 'closed_at' => $this->businessNow('d/m/Y h:i A'),
            'cashier' => 'María González', 'channels' => $channels,
            'payment_methods' => [
                ['label' => 'Efectivo', 'amount' => 2040.00],
                ['label' => 'Tarjeta', 'amount' => 970.00],
                ['label' => 'Transferencia', 'amount' => 235.50],
            ],
            'sales_total' => 3245.50, 'initial_amount' => 500.00, 'cash_sales' => 2040.00, 'cash_incomes' => 0.00,
            'cash_expenses' => 180.00, 'expected_cash' => 2360.00, 'declared_cash' => 2350.00,
            'difference' => -10.00, 'notes' => 'Diferencia revisada por gerencia.',
            'generated_at' => $this->businessNow('d/m/Y h:i A'),
        ];
    }

    private function businessTimezone(): string
    {
        return (string) config('app.business_timezone', 'America/Mexico_City');
    }

    private function businessNow(string $format = 'd/m/Y h:i A'): string
    {
        return now($this->businessTimezone())->format($format);
    }

    private function businessDate(?DateTimeInterface $date, string $format = 'd/m/Y h:i A'): ?string
    {
        return $date
            ? CarbonImmutable::instance($date)->setTimezone($this->businessTimezone())->format($format)
            : null;
    }

    public function render(string $type, array $payload, ?TicketTemplate $template = null, ?BusinessSetting $business = null): string
    {
        $business ??= BusinessSetting::current();
        $template ??= TicketTemplate::current($type);
        $qrDataUri = $template->show_qr && ! empty($payload['tracking_url'])
            ? $this->qrDataUri($payload['tracking_url'])
            : null;

        return view('print.ticket-document', compact('business', 'template', 'payload', 'qrDataUri'))->render();
    }

    private function renderBatch(string $type, array $payloads, bool $autoPrint = true): string
    {
        $business = BusinessSetting::current();
        $template = TicketTemplate::current($type);

        return view('print.ticket-batch', compact('business', 'template', 'payloads', 'autoPrint'))->render();
    }

    private function qrDataUri(string $value): string
    {
        $renderer = new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($value);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

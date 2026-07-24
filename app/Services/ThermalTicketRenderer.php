<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\CashRegisterCut;
use App\Models\InventoryItem;
use App\Models\InventoryPurchase;
use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\Order;
use App\Models\TicketTemplate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ThermalTicketRenderer
{
    public function renderOrder(
        Order $order,
        string $type,
        ?string $printArea = null,
        bool $autoPrint = true,
    ): string
    {
        $order->loadMissing(['items.addons', 'items.ingredients', 'items.product.category.printArea', 'seller', 'payments', 'customer', 'mesa.area']);

        $items = $order->items->filter(fn ($item) => ! (bool) $item->is_cancelled);

        if ($type === 'kitchen_area' && $printArea === null) {
            $areas = $items->groupBy(fn ($item) => (string) ($item->product?->category?->printArea?->name ?? 'General'));
            if ($areas->count() > 1) {
                return $this->renderBatch('kitchen_area', $areas->map(
                    fn ($areaItems, $areaName) => array_merge(
                        $this->orderPayload($order, $type, (string) $areaName, $areaItems),
                        ['auto_print' => $autoPrint],
                    )
                )->values()->all(), $autoPrint);
            }
        }

        if ($type === 'kitchen_area' && $printArea) {
            $items = $items->filter(
                fn ($item) => strcasecmp((string) ($item->product?->category?->printArea?->name ?? 'General'), $printArea) === 0
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
            'date' => $order->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'customer' => $order->display_name,
            'served_by' => $order->seller?->name,
            'table' => $order->table_identifier,
            'notes' => $order->notes,
            'items' => collect($items)->map(fn ($item) => [
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
            'tracking_url' => $order->public_token ? route('kiosk.track', $order->public_token) : null,
        ];
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
            'date' => $purchase->issued_at?->format('d/m/Y H:i'),
            'requested_by' => $purchase->requester?->name ?: 'Sin asignar',
            'received_by' => $purchase->receiver?->name,
            'received_at' => $purchase->received_at?->format('d/m/Y H:i'),
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
    ): string {
        $mesa->loadMissing('area');

        return $this->render('customer', [
            'title' => 'TICKET DE MESA',
            'folio' => $accountLabel,
            'date' => now()->format('d/m/Y H:i'),
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
            'payments' => collect($payments)->map(fn ($payment) => [
                'label' => match ($payment['method'] ?? '') {
                    'cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia',
                    default => ucfirst($payment['method'] ?? 'Pago'),
                },
                'amount' => (float) ($payment['amount'] ?? 0),
                'change' => (float) ($payment['cash_change'] ?? 0),
            ])->all(),
            'total' => $total,
            'tracking_url' => null,
        ]);
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
                'date' => now()->format('d/m/Y H:i'),
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
            'date' => now()->format('d/m/Y H:i'),
            'customer' => 'Cliente de ejemplo',
            'served_by' => 'Usuario de caja',
            'cashier' => 'Usuario de caja',
            'table' => $type === 'customer' ? 'Mesa 4' : null,
            'area' => 'Salón',
            'notes' => 'Sin cebolla',
            'items' => [
                ['name' => 'Hamburguesa especial', 'quantity' => 1, 'subtotal' => 145, 'modifiers' => [['name' => '+ Queso extra', 'price' => 15]], 'notes' => null],
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
            'opened_at' => $cut->cashRegister?->opened_at?->format('d/m/Y g:i A'),
            'closed_at' => $cut->generated_at?->format('d/m/Y g:i A'),
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
            'cash_expenses' => (float) $cut->total_expenses_cash,
            'expected_cash' => (float) $cut->expected_cash,
            'declared_cash' => (float) $cut->declared_cash,
            'difference' => (float) $cut->difference,
            'notes' => $cut->cashRegister?->closing_notes,
            'generated_at' => $cut->generated_at?->format('d/m/Y g:i A'),
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
            'opened_at' => now()->subHours(8)->format('d/m/Y g:i A'), 'closed_at' => now()->format('d/m/Y g:i A'),
            'cashier' => 'María González', 'channels' => $channels,
            'payment_methods' => [
                ['label' => 'Efectivo', 'amount' => 2040.00],
                ['label' => 'Tarjeta', 'amount' => 970.00],
                ['label' => 'Transferencia', 'amount' => 235.50],
            ],
            'sales_total' => 3245.50, 'initial_amount' => 500.00, 'cash_sales' => 2040.00,
            'cash_expenses' => 180.00, 'expected_cash' => 2360.00, 'declared_cash' => 2350.00,
            'difference' => -10.00, 'notes' => 'Diferencia revisada por gerencia.',
            'generated_at' => now()->format('d/m/Y g:i A'),
        ];
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
        $renderer = new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($value);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

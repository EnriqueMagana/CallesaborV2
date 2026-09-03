<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'blocks' => 'array',
        'options' => 'array',
        'show_logo' => 'boolean',
        'show_qr' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const TYPES = [
        'customer' => ['name' => 'Cliente', 'icon' => 'bx-user'],
        'counter' => ['name' => 'Ventanilla', 'icon' => 'bx-store-alt'],
        'delivery' => ['name' => 'Delivery', 'icon' => 'bx-cycling'],
        'cash_cut' => ['name' => 'Corte de caja', 'icon' => 'bx-calculator'],
        'kitchen_area' => ['name' => 'Áreas / Cocina', 'icon' => 'bx-dish'],
        'inventory_purchase' => ['name' => 'Compra de inventario', 'icon' => 'bx-package'],
    ];

    public static function defaultsFor(string $key): array
    {
        if ($key === 'cash_cut') {
            return [
                'key' => $key,
                'name' => self::TYPES[$key]['name'],
                'paper_width_mm' => 80,
                'font_size' => 11,
                'margin_mm' => 4,
                'show_logo' => false,
                'show_qr' => false,
                'qr_label' => null,
                'footer_text' => 'Documento de control interno',
                'blocks' => self::cashCutBlocks(),
                'options' => [
                    'printer_dpi' => 'auto',
                    'show_rfc' => true,
                    'show_phone' => true,
                    'show_address' => true,
                    'logo_width_mm' => 42,
                ],
                'is_active' => true,
            ];
        }

        if ($key === 'inventory_purchase') {
            return [
                'key' => $key,
                'name' => self::TYPES[$key]['name'],
                'paper_width_mm' => 80,
                'font_size' => 11,
                'margin_mm' => 4,
                'show_logo' => false,
                'show_qr' => false,
                'qr_label' => null,
                'footer_text' => 'Conserva este folio para recepcionar los insumos.',
                'blocks' => [
                    ['key' => 'header', 'label' => 'Encabezado y logo', 'enabled' => true],
                    ['key' => 'business', 'label' => 'Información del negocio', 'enabled' => true],
                    ['key' => 'inventory_purchase_meta', 'label' => 'Folio y responsable', 'enabled' => true],
                    ['key' => 'inventory_purchase_items', 'label' => 'Insumos solicitados', 'enabled' => true],
                    ['key' => 'inventory_purchase_notes', 'label' => 'Indicaciones de compra', 'enabled' => true],
                    ['key' => 'footer', 'label' => 'Pie del ticket', 'enabled' => true],
                ],
                'options' => [
                    'printer_dpi' => 'auto',
                    'show_rfc' => true,
                    'show_phone' => true,
                    'show_address' => true,
                    'logo_width_mm' => 42,
                ],
                'is_active' => true,
            ];
        }

        $blocks = [
            ['key' => 'header', 'label' => 'Encabezado y logo', 'enabled' => true],
            ['key' => 'business', 'label' => 'Información del negocio', 'enabled' => true],
            ['key' => 'order_meta', 'label' => 'Datos del pedido', 'enabled' => true],
            ['key' => 'delivery', 'label' => 'Datos de entrega', 'enabled' => $key === 'delivery'],
            ['key' => 'items', 'label' => 'Productos', 'enabled' => $key !== 'cash_cut'],
            ['key' => 'cut_summary', 'label' => 'Resumen del corte', 'enabled' => $key === 'cash_cut'],
            ['key' => 'totals', 'label' => 'Totales', 'enabled' => ! in_array($key, ['kitchen_area', 'cash_cut'], true)],
            ['key' => 'payments', 'label' => 'Formas de pago', 'enabled' => in_array($key, ['customer', 'counter', 'delivery'], true)],
            ['key' => 'qr', 'label' => 'Código QR de seguimiento', 'enabled' => false],
            ['key' => 'footer', 'label' => 'Pie del ticket', 'enabled' => $key !== 'kitchen_area'],
        ];

        return [
            'key' => $key,
            'name' => self::TYPES[$key]['name'] ?? ucfirst($key),
            'paper_width_mm' => 80,
            'font_size' => $key === 'kitchen_area' ? 13 : 12,
            'margin_mm' => 4,
            'show_logo' => false,
            'show_qr' => false,
            'qr_label' => 'Escanea para consultar tu pedido',
            'footer_text' => $key === 'kitchen_area' ? '' : '¡Gracias por tu preferencia!',
            'blocks' => $blocks,
            'options' => [
                'printer_dpi' => 'auto',
                'show_rfc' => true,
                'show_phone' => true,
                'show_address' => true,
                'logo_width_mm' => 42,
                'item_font_family' => 'courier',
                'item_font_size' => $key === 'kitchen_area' ? 18 : 12,
            ],
            'is_active' => true,
        ];
    }

    public static function cashCutBlocks(): array
    {
        return [
            ['key' => 'header', 'label' => 'Encabezado y logo', 'enabled' => true],
            ['key' => 'business', 'label' => 'Información del negocio', 'enabled' => true],
            ['key' => 'cut_meta', 'label' => 'Caja, folio y responsable', 'enabled' => true],
            ['key' => 'cut_sales_channels', 'label' => 'Ventas por canal', 'enabled' => true],
            ['key' => 'cut_payment_methods', 'label' => 'Resumen por forma de pago', 'enabled' => true],
            ['key' => 'cut_cash_movements', 'label' => 'Movimientos de efectivo', 'enabled' => true],
            ['key' => 'cut_reconciliation', 'label' => 'Conciliación y diferencia', 'enabled' => true],
            ['key' => 'cut_notes', 'label' => 'Notas del cierre', 'enabled' => true],
            ['key' => 'footer', 'label' => 'Pie del ticket', 'enabled' => true],
        ];
    }

    public static function current(string $key): self
    {
        return static::query()->firstOrCreate(['key' => $key], static::defaultsFor($key));
    }

    public static function ensureDefaults(): void
    {
        foreach (array_keys(static::TYPES) as $key) {
            static::current($key);
        }
    }
}

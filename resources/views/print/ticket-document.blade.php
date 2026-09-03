<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $payload['title'] ?? $template->name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/ticket-print.css') }}?v={{ filemtime(public_path('assets/css/ticket-print.css')) }}">
</head>
@php
    $configuredLogoWidth = (int) ($template->options['logo_width_mm'] ?? 42);
    $logoWidth = in_array($configuredLogoWidth, [12, 18, 24, 30, 36, 42, 48, 54], true) ? $configuredLogoWidth : 42;
    $configuredItemFont = $template->key === 'kitchen_area'
        ? (string) ($template->options['item_font_family'] ?? 'courier')
        : 'courier';
    $itemFontFamily = in_array($configuredItemFont, ['courier', 'arial', 'verdana', 'system'], true) ? $configuredItemFont : 'courier';
    $itemFontSize = $template->key === 'kitchen_area'
        ? min(28, max(12, (int) ($template->options['item_font_size'] ?? 18)))
        : (int) $template->font_size;
    $configuredPrinterDpi = (string) ($template->options['printer_dpi'] ?? 'auto');
    $printerDpi = in_array($configuredPrinterDpi, ['auto', '203', '300'], true) ? $configuredPrinterDpi : 'auto';
@endphp
<body data-printer-dpi="{{ $printerDpi }}" class="ticket-document ticket-dpi-{{ $printerDpi }} ticket-paper-{{ $template->paper_width_mm }} ticket-font-{{ $template->font_size }} ticket-margin-{{ $template->margin_mm }} ticket-logo-size-{{ $logoWidth }} ticket-items-font-{{ $itemFontFamily }} ticket-items-size-{{ $itemFontSize }}">
    <main class="ticket-sheet">
        @foreach($template->blocks as $block)
            @if(($block['enabled'] ?? false) && in_array($block['key'] ?? '', ['header','business','order_meta','delivery','items','cut_summary','cut_meta','cut_sales_channels','cut_payment_methods','cut_cash_movements','cut_reconciliation','cut_notes','totals','payments','qr','footer','inventory_purchase_meta','inventory_purchase_items','inventory_purchase_notes'], true))
                @include('print.blocks.'.$block['key'])
            @endif
        @endforeach
    </main>
    @if(!($payload['preview'] ?? false) && ($payload['auto_print'] ?? true))
        <script>window.addEventListener('load', () => window.print())</script>
    @endif
</body>
</html>

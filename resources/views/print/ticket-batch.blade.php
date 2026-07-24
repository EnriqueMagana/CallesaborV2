<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $template->name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/ticket-print.css') }}?v={{ filemtime(public_path('assets/css/ticket-print.css')) }}">
</head>
<body class="ticket-document ticket-paper-{{ $template->paper_width_mm }} ticket-font-{{ $template->font_size }} ticket-margin-{{ $template->margin_mm }}">
    @foreach($payloads as $payload)
        @php $qrDataUri = null; @endphp
        <main class="ticket-sheet ticket-page">
            @foreach($template->blocks as $block)
                @if(($block['enabled'] ?? false) && in_array($block['key'] ?? '', ['header','business','order_meta','delivery','items','cut_summary','cut_meta','cut_sales_channels','cut_payment_methods','cut_cash_movements','cut_reconciliation','cut_notes','totals','payments','qr','footer'], true))
                    @include('print.blocks.'.$block['key'])
                @endif
            @endforeach
        </main>
    @endforeach
    @if($autoPrint)
        <script>window.addEventListener('load', () => window.print())</script>
    @endif
</body>
</html>

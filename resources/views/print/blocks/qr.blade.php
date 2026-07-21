@if($template->show_qr && $qrDataUri)
<section class="ticket-qr">
    <img src="{{ $qrDataUri }}" alt="Código QR para seguimiento del pedido">
    <span>{{ $template->qr_label }}</span>
</section>
<hr>
@endif

@extends('mail.layouts.branded')

@section('title', 'Prueba de correo completada')
@section('preheader', 'La configuración de correo de Calle Sabor funciona correctamente.')

@section('content')
    <h1 style="margin:0 0 20px;color:#252a26;font-size:25px;font-weight:700;line-height:1.25;text-align:center;">El envío de correo funciona</h1>
    <p style="margin:0 0 22px;color:#4d5550;font-size:15px;line-height:1.7;text-align:center;">Esta prueba confirma que <strong>{{ $brandName }}</strong> pudo entregar un mensaje mediante la configuración actual de Resend.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px;border:1px solid #dfe8df;border-radius:9px;background-color:#f5f8f5;">
        <tr><td style="padding:15px 17px 7px;color:#6b746d;font-size:12px;">Solicitada por</td><td align="right" style="padding:15px 17px 7px;color:#314c37;font-size:13px;font-weight:700;">{{ $testerName }}</td></tr>
        <tr><td style="padding:7px 17px;color:#6b746d;font-size:12px;">Entorno</td><td align="right" style="padding:7px 17px;color:#314c37;font-size:13px;font-weight:700;">{{ app()->environment() }}</td></tr>
        <tr><td style="padding:7px 17px 15px;color:#6b746d;font-size:12px;">Fecha</td><td align="right" style="padding:7px 17px 15px;color:#314c37;font-size:13px;font-weight:700;">{{ $sentAt }}</td></tr>
    </table>

    <p style="margin:0;color:#5f6761;font-size:13px;line-height:1.7;text-align:center;">No necesitas realizar ninguna acción.<br>Este mensaje fue generado desde el Centro técnico.</p>
@endsection

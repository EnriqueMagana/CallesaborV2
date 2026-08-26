@extends('mail.layouts.branded')

@section('title', 'Restablece tu contraseña')
@section('preheader', 'Recibimos una solicitud para restablecer la contraseña de tu cuenta.')

@section('content')
    <h1 style="margin:0 0 22px;color:#252a26;font-size:25px;font-weight:700;line-height:1.25;text-align:center;">Restablece tu contraseña</h1>
    <p style="margin:0 0 16px;color:#48664e;font-size:16px;font-weight:700;line-height:1.6;">Hola, {{ $userName }}:</p>
    <p style="margin:0 0 13px;color:#4d5550;font-size:15px;line-height:1.7;">Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>
    <p style="margin:0 0 24px;color:#4d5550;font-size:15px;line-height:1.7;">Haz clic en el botón para crear una nueva contraseña.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding:0 0 24px;">
                <a href="{{ $actionUrl }}" target="_blank" rel="noopener" style="display:inline-block;min-width:220px;padding:15px 24px;color:#ffffff;background-color:#5f8065;border:1px solid #5f8065;border-radius:7px;font-size:15px;font-weight:700;line-height:1.2;text-align:center;text-decoration:none;">Restablecer contraseña</a>
            </td>
        </tr>
    </table>

    <div style="margin:0 0 22px;padding:13px 16px;border-radius:8px;background-color:#f0f5f0;color:#4c6250;font-size:13px;line-height:1.6;text-align:center;">
        Este enlace expirará en <strong>{{ $expiresInMinutes }} minutos</strong> y solo puede utilizarse una vez.
    </div>

    <p style="margin:0;color:#5f6761;font-size:13px;line-height:1.7;text-align:center;">Si no solicitaste este cambio, ignora este correo de forma segura.<br>Tu contraseña no se modificará.</p>

    <p style="margin:24px 0 7px;color:#7a827c;font-size:11px;line-height:1.5;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
    <p style="margin:0;color:#5f8065;font-size:11px;line-height:1.5;word-break:break-all;"><a href="{{ $actionUrl }}" style="color:#5f8065;text-decoration:underline;">{{ $actionUrl }}</a></p>
@endsection

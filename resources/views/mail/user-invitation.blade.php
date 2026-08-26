@extends('mail.layouts.branded')

@section('title', 'Invitación al equipo')
@section('preheader', 'Completa tu registro para integrarte al equipo de ' . $brandName . ' como ' . $roleLabel . '.')

@section('content')
    <h1 style="margin:0 0 20px;color:#252a26;font-size:25px;font-weight:700;line-height:1.25;text-align:center;">¡Bienvenido al equipo!</h1>
    <p style="margin:0 0 15px;color:#48664e;font-size:16px;font-weight:700;line-height:1.6;text-align:center;">Hola:</p>
    <p style="margin:0 0 18px;color:#4d5550;font-size:15px;line-height:1.7;text-align:center;">{{ $invitedByName }} te invitó a formar parte de <strong>{{ $brandName }}</strong>.</p>

    <div style="margin:0 0 22px;padding:15px 18px;border:1px solid #dce7dd;border-radius:9px;background-color:#f3f7f3;text-align:center;">
        <span style="display:block;margin-bottom:5px;color:#718075;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Rol asignado</span>
        <strong style="color:#35533b;font-size:18px;line-height:1.4;">{{ $roleLabel }}</strong>
    </div>

    <p style="margin:0 0 24px;color:#4d5550;font-size:14px;line-height:1.7;text-align:center;">Completa el registro guiado para añadir tus datos, fotografía opcional y contraseña de acceso.</p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding:0 0 24px;">
                <a href="{{ $invitationUrl }}" target="_blank" rel="noopener" style="display:inline-block;min-width:220px;padding:15px 24px;color:#ffffff;background-color:#5f8065;border:1px solid #5f8065;border-radius:7px;font-size:15px;font-weight:700;line-height:1.2;text-align:center;text-decoration:none;">Completar mi registro</a>
            </td>
        </tr>
    </table>

    <div style="margin:0 0 20px;padding:13px 16px;border-radius:8px;background-color:#fff7e8;color:#765414;font-size:13px;line-height:1.6;text-align:center;">
        <strong>Este enlace vence en 1 hora.</strong><br>Estará disponible hasta {{ $expiresAt }}.
    </div>

    <p style="margin:0;color:#5f6761;font-size:13px;line-height:1.7;text-align:center;">Si no esperabas esta invitación, puedes ignorar el mensaje de forma segura.</p>

    <p style="margin:24px 0 7px;color:#7a827c;font-size:11px;line-height:1.5;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
    <p style="margin:0;color:#5f8065;font-size:11px;line-height:1.5;word-break:break-all;"><a href="{{ $invitationUrl }}" style="color:#5f8065;text-decoration:underline;">{{ $invitationUrl }}</a></p>
@endsection

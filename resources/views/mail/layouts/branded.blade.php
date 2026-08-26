<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>@yield('title')</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f3;color:#292d2a;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    @php
        $renderedLogoUrl = isset($message) ? $message->embed($logoPath) : $logoUrl;
    @endphp
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">@yield('preheader')</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background-color:#f4f6f3;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background-color:#ffffff;border:1px solid #e2e7e1;border-radius:16px;box-shadow:0 8px 30px rgba(48,67,51,.08);">
                    <tr>
                        <td align="center" style="padding:34px 32px 18px;">
                            <img src="{{ $renderedLogoUrl }}" width="108" height="108" alt="Logo de {{ $brandName }}" style="display:block;width:108px;height:108px;margin:0 auto;border:0;outline:none;text-decoration:none;">
                            <div style="margin-top:10px;color:#314c37;font-family:Georgia,'Times New Roman',serif;font-size:32px;font-weight:700;line-height:1.15;letter-spacing:-.5px;">{{ $brandName }}</div>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;">
                                <tr>
                                    <td style="width:48px;height:1px;background-color:#86a18a;font-size:0;line-height:0;">&nbsp;</td>
                                    <td style="padding:0 7px;color:#66816b;font-size:14px;line-height:1;">•</td>
                                    <td style="width:48px;height:1px;background-color:#86a18a;font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 44px 40px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:22px 32px;border-top:1px solid #e5e9e4;background-color:#f8faf8;border-radius:0 0 16px 16px;">
                            <p style="margin:0;color:#6b746d;font-size:12px;line-height:1.6;">© {{ now()->year }} {{ $brandName }}. Todos los derechos reservados.</p>
                            <p style="margin:5px 0 0;color:#8a928c;font-size:11px;line-height:1.5;">Mensaje automático; por favor, no compartas enlaces de seguridad.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

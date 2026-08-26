{{ $brandName }}

Restablece tu contraseña

Hola, {{ $userName }}:

Recibimos una solicitud para restablecer la contraseña de tu cuenta.

Abre este enlace para crear una nueva contraseña:
{{ $actionUrl }}

Este enlace expirará en {{ $expiresInMinutes }} minutos y solo puede utilizarse una vez.

Si no solicitaste este cambio, ignora este correo. Tu contraseña no se modificará.

© {{ now()->year }} {{ $brandName }}

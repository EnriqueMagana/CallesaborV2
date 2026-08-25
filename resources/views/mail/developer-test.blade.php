<x-mail::message>
# El envío de correo funciona

Esta prueba confirma que **{{ config('app.name') }}** pudo entregar un mensaje mediante la configuración actual de Resend.

<x-mail::panel>
Solicitada por: {{ $testerName }}  
Entorno: {{ app()->environment() }}  
Fecha: {{ $sentAt }}
</x-mail::panel>

No necesitas realizar ninguna acción. Este mensaje fue generado desde el Centro técnico.

Gracias,  
{{ config('app.name') }}
</x-mail::message>

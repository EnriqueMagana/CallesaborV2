# Centro de notificaciones: despliegue

El centro no requiere WebSockets, Reverb, Firebase, workers de cola ni procesos de polling.
Las notificaciones se escriben de forma síncrona después de confirmar la transacción del pedido.

## Publicación

1. Publicar el código.
2. Ejecutar `php artisan migrate --force`.
3. Limpiar cachés de la aplicación con `php artisan optimize:clear`.
4. Confirmar que el cron de Laravel ejecute `php artisan schedule:run` cada minuto.

El scheduler elimina diariamente, a las 03:20, las notificaciones con más de 30 días. Las
preferencias personales se administran en **Perfil → Notificaciones**. Si el hosting no ejecuta
el scheduler, el panel seguirá funcionando; únicamente no se hará la limpieza automática.

## Comprobaciones rápidas

- Crear un pedido de mesa: cocina y responsables deben recibirlo; repartidores no.
- Marcar el pedido de mesa como listo: debe avisarse al mesero responsable.
- Crear un delivery: el repartidor no debe recibirlo mientras esté pendiente o en preparación.
- Marcar el delivery como listo: debe aparecer para los repartidores disponibles.
- Abrir otra sección con navegación Livewire: la campana debe actualizarse sin polling.

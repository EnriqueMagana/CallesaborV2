# Centro de notificaciones: despliegue

MySQL sigue siendo la fuente de verdad. Firebase Realtime Database funciona únicamente como
canal efímero para avisar al navegador; no hay polling, Reverb ni workers obligatorios. Si Firebase
falla o está desactivado, el centro continúa usando los eventos y la navegación de Livewire.

## Publicación

1. Publicar el código.
2. Ejecutar `php artisan migrate --force`.
3. Configurar Firebase como se describe abajo.
4. Ejecutar `npm ci && npm run build`.
5. Limpiar cachés con `php artisan optimize:clear`.
6. Confirmar que el cron de Laravel ejecute `php artisan schedule:run` cada minuto.

El scheduler elimina a las 23:59 (America/Mexico_City) todas las señales bajo `/notifications`
en Realtime Database y a las 03:20 las notificaciones MySQL con más de 30 días. Si el cron no
corre, el panel sigue funcionando, pero no se hará la limpieza automática.

## Configuración de Firebase

1. En Firebase Console, abrir **Project settings → General** y conservar la configuración web en
   `firebaseconfig.txt` (o indicar su ruta con `FIREBASE_WEB_CONFIG_PATH`).
2. Crear Realtime Database y copiar su URL exacta a `FIREBASE_DATABASE_URL`.
3. Abrir **Project settings → Service accounts → Generate new private key**. Guardar el JSON fuera
   del repositorio, por defecto en `storage/app/firebase-service-account.json`.
4. En **Realtime Database → Rules**, pegar `firebase.database.rules.json` y publicar.
5. Ejecutar `php artisan migrate --force` para crear las tablas de Pulse.
6. Cambiar `FIREBASE_REALTIME_ENABLED=true`, limpiar configuración y comprobar con
   `php artisan notifications:clear-realtime`.

Nunca usar reglas públicas ni subir el JSON de la cuenta de servicio al repositorio. El dashboard
de diagnóstico queda en `/pulse` y solamente pueden abrirlo owner, super-admin, admin y gerente.

## Comprobaciones rápidas

- Crear un pedido de mesa: cocina y responsables deben recibirlo; repartidores no.
- Marcar el pedido de mesa como listo: debe avisarse al mesero responsable.
- Crear un delivery: el repartidor no debe recibirlo mientras esté pendiente o en preparación.
- Marcar el delivery como listo: debe aparecer para los repartidores disponibles.
- Abrir dos sesiones autorizadas: la segunda debe actualizarse por RTDB sin recargar.
- Bloquear temporalmente Firebase: el pedido debe guardarse y Livewire debe continuar activo.
- Revisar `/pulse`: la indisponibilidad debe aparecer como excepción con operación y fallback.

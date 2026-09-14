# Calle Sabor V2

Sistema de gestión para restaurante: punto de venta, servicio en mesas, delivery,
kiosco de autoservicio, caja con cortes de turno, inventario, reservas y un panel
administrativo con permisos por rol.

Es una aplicación Laravel monolítica con interfaz Livewire. No hay SPA ni API
pública: cada pantalla es un componente Livewire que se renderiza en el servidor.

---

## Stack

| Pieza | Versión | Notas |
|---|---|---|
| PHP | 8.2+ | |
| Laravel | 12 | |
| Livewire | 3.8 | más Volt para algunas vistas de autenticación |
| Alpine.js | el que trae Livewire | es toda la interactividad del POS; Bootstrap JS no se carga ahí |
| MySQL | producción (`callesabor`) | SQLite para local y pruebas |
| Tailwind + Vite | 4 / 7 | conviven con el CSS del tema (Bootstrap) |
| Laravel Fortify | autenticación | con `EnsureSingleSession` encima |
| spatie/laravel-permission | roles y permisos | catálogo propio en `app/Support/PermissionCatalog.php` |
| barryvdh/laravel-dompdf | PDF | tickets y reportes |
| Laravel Pulse | diagnóstico | apagado en la terminal del POS |
| Firebase Realtime Database | canal de avisos | opcional; MySQL es la fuente de verdad |
| Resend | correo saliente | invitaciones y verificación |

---

## Módulos

Todas las pantallas administrativas viven bajo `/app` y exigen sesión activa,
usuario habilitado, permiso del módulo y —según configuración— caja abierta.

| Módulo | Ruta | Componente |
|---|---|---|
| Punto de venta | `/app/pos` | `Pos\PointOfSale` + `Pos\Catalog` + `Pos\Panels\KitchenPanel` |
| Mesas | `/app/mesas` | `Mesas\GestionMesas`, `MesaOrden`, `MesaOrdenes`, `SplitCuenta` |
| Delivery | `/app/delivery` | `Delivery\DeliveryBoard` |
| Caja y cortes | `/app/caja` | `Caja\Dashboard`, `CorteDeCaja`, `CorteHistorial`, `CorteDetalle` |
| Órdenes e historial | `/app/ordenes`, `/app/historial-ventas` | `Orders\*` |
| Solicitudes de cambio | `/app/solicitudes-ordenes` | `Orders\OrderChangeRequestInbox`, `OrderChangeRequestWizard` |
| Inventario | `/app/inventario` | `Inventory\InventoryManager` |
| Menú y catálogo | `/app/constructor-menu`, `/app/menu-digital` | `Menu\MenuBuilder`, `Admin\DigitalMenuManager` |
| Promociones y descuentos | `/app/promociones`, `/app/descuentos` | `Admin\PromotionManager`, `Admin\DiscountManager` |
| Clientes | `/app/clientes` | `Customers\CustomerManager` |
| Reservas | `/app/reservas` | `Reservas\CalendarioReservas` |
| Usuarios, roles y permisos | `/app/usuarios`, `/app/roles-permisos` | `Admin\UserList`, `Admin\RolePermissionManager` |
| Configuración del negocio | `/app/configuracion-negocio` | `Admin\BusinessSettingsManager` |
| Kioscos (administración) | `/app/kioscos` | `Admin\KioskSettings` |
| Consola de desarrollo | `/app/super-admin` | `SuperAdmin\DeveloperConsole`, `EnvironmentSettings` |

Rutas públicas, sin sesión:

| Qué | Ruta |
|---|---|
| Sitio público (inicio, menú, horarios, galería, contacto) | `/`, `/menu`, `/horarios`, `/galeria`, `/contacto` |
| Kiosco de autoservicio | `/kiosco/{token}` |
| Seguimiento de pedido para el cliente | `/pedido/{publicToken}` |
| Aceptar invitación de usuario | `/invitacion/{invitation}/{token}` |
| Health check | `/up` |

---

## Organización del código

```
app/
├── Livewire/            una carpeta por módulo; las pantallas
│   └── Pos/
│       ├── PointOfSale.php      componente raíz del POS
│       ├── Catalog.php          catálogo (hijo, se refresca solo)
│       ├── Panels/              paneles operativos (cocina, etc.)
│       └── Concerns/            carrito, cobro, clientes, promociones, cotizaciones
├── Services/            reglas de negocio reutilizables
│   └── Firebase/        configuración, tokens y acceso a Realtime Database
├── Support/             objetos de valor y catálogos sin estado
│   ├── Pos/CartState.php        totales del carrito, testeable sin Livewire
│   ├── PermissionCatalog.php    los permisos del sistema, agrupados por módulo
│   ├── NotificationEventCatalog.php
│   ├── BusinessTime.php         zona horaria del negocio vs. la de almacenamiento
│   └── AssetVersion.php         cache-busting de los assets públicos
├── Http/Middleware/     sesión única, usuario activo, acceso por módulo, caja abierta
└── Models/              ~55 modelos Eloquent
```

`PointOfSale` es el componente más grande del sistema y está dividido en
*concerns* por dominio (`ManagesCart`, `ManagesCheckout`, `ManagesCustomers`,
`ManagesPromotions`, `ManagesQuotations`). El criterio de reparto y las
decisiones de rendimiento están documentados en
[`OPTIMIZACION-POS.md`](OPTIMIZACION-POS.md).

### Cadena de middleware

Todo `/app` pasa por, en orden: `auth` → `EnsureUserIsActive` →
`PreventBackHistory` → `EnforceSidebarModuleAccess` →
`RequireOpenCashRegisterForConfiguredModules` → `verified`.

Globalmente, sobre el grupo `web`: `SecurityHeaders` (nosniff, `X-Frame-Options:
DENY`, HSTS en producción) y `EnsureSingleSession` (una sesión por usuario; al
entrar en otro dispositivo se cierra la anterior).

---

## Puesta en marcha local

```bash
composer setup     # install + .env + key:generate + migrate + npm install + npm run build
composer dev       # servidor, cola, logs y Vite en paralelo
```

Si prefieres hacerlo a mano:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed              # roles, permisos, menú del sidebar, mesas
npm install && npm run build
php artisan serve
```

Los seeders relevantes son `RolesAndPermissionsSeeder` (sincroniza el catálogo de
permisos), `SidebarMenuSeeder`, `MesasSeeder` y los dos `Legacy*` de datos de
arranque.

---

## Configuración que importa

`.env.example` está anotado: varias claves no son preferencia sino rendimiento
medido en la terminal del POS.

| Clave | Valor en el POS | Por qué |
|---|---|---|
| `APP_DEBUG` | `false` | con `true`, Laravel guarda el backtrace de cada consulta |
| `CACHE_STORE` | `file` (o `redis`) | con `database` son 5 consultas extra por click |
| `SESSION_DRIVER` | `file` | `database` solo si hay varios servidores web |
| `PULSE_ENABLED` | `false` | agrega 2 `INSERT` por request |
| `BUSINESS_TIMEZONE` | `America/Mexico_City` | zona del negocio; `APP_TIMEZONE` es la de almacenamiento |
| `PROMOTION_RULE_ENGINE_ENABLED` | `true` | motor de precios automáticos de promociones |

`BUSINESS_TIMEZONE` y `APP_TIMEZONE` son distintas a propósito: las fechas se
guardan en una zona y se muestran en la otra a través de `Support\BusinessTime`.

---

## Notificaciones en tiempo real

MySQL es la fuente de verdad (`AppNotification`). Firebase Realtime Database se
usa solo como canal efímero para avisar al navegador de que hay algo nuevo: el
payload que viaja son tres campos (`id`, `event_key`, `created_at_ms`), sin datos
de clientes ni de pedidos. Si Firebase está apagado o falla, el centro de
notificaciones sigue funcionando con los eventos de Livewire.

El navegador no recibe credenciales de larga vida: pide una sesión a
`/app/notifications/realtime-session` (autenticada, limitada a 20 por minuto) y
recibe un *custom token* firmado en el servidor con la cuenta de servicio, válido
una hora.

Configuración y publicación paso a paso en
[`docs/notifications-deployment.md`](docs/notifications-deployment.md). Los dos
secretos (`firebaseconfig.txt` y el JSON de la cuenta de servicio) viven en
`storage/app/` y están fuera del control de versiones.

---

## Kiosco de autoservicio

Cada terminal tiene un token propio; la pantalla del kiosco es pública y se
identifica solo por ese token en la URL.

```bash
php artisan kiosk:issue-token [nombre]   # crea el terminal y muestra su URL; el token se ve una sola vez
php artisan kiosk:revoke-token           # revoca el token de un terminal de inmediato
```

El QR para el cliente lo genera `Services\KioskQrCode`, y el pedido se sigue
desde `/pedido/{publicToken}` sin necesidad de cuenta.

---

## Impresión

`Services\ThermalTicketRenderer` arma todos los tickets a partir de plantillas
editables (`TicketTemplate`), y las rutas bajo `/print` los sirven listos para la
impresora térmica: comanda de cocina, ventanilla, delivery, ticket de cliente,
compra de inventario y corte de caja. Las áreas de impresión se configuran por
`PrintArea`.

---

## Permisos

Los permisos no se crean a mano en la base: viven en
`app/Support/PermissionCatalog.php`, agrupados en 16 módulos (`punto_venta`,
`usuarios`, `clientes`, `menu`, `promociones`, `descuentos`, `ordenes`, `mesas`,
`caja`, `reportes`, `configuracion`, `kiosco`, `delivery`, `reservas`,
`inventario`, `desarrollo`), cada uno con una descripción operativa que es la que
se muestra al asignar roles. Para agregar un permiso: declararlo en el catálogo y
volver a correr `RolesAndPermissionsSeeder`.

El acceso a cada pantalla se resuelve en dos capas: el permiso de spatie
(`can:...` en la ruta) y la visibilidad del módulo en el sidebar
(`SidebarModuleAccess` + `EnforceSidebarModuleAccess`).

---

## Despliegue

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
composer deploy
```

`composer deploy` hace dos cosas: ejecuta `scripts/build-pos-assets.php` —que
minifica el CSS y el JS que carga la terminal del POS, porque
`layouts/pos.blade.php` referencia los `.min`— y luego reconstruye las cachés de
configuración, rutas, vistas y eventos.

El cron del servidor debe ejecutar `php artisan schedule:run` cada minuto. El
scheduler corre dos tareas: purga las notificaciones de más de 30 días (03:20) y
limpia las señales de Firebase (`notifications:clear-realtime`, a la hora que
indique `FIREBASE_REALTIME_CLEANUP_TIME`).

Si editas un CSS o un JS del POS y olvidas reconstruir, el POS seguirá sirviendo
el `.min` viejo: corre `composer build-pos-assets`.

---

## Pruebas

```bash
composer test        # config:clear + artisan test
```

La suite (PHPUnit 11, `tests/Unit` y `tests/Feature`) **no está versionada**: la
carpeta `tests/` está en `.gitignore` por decisión del equipo, así que existe solo
en cada máquina de desarrollo. Tampoco hay integración continua.

Hay una trampa conocida: `config:cache` y las pruebas no se llevan bien. Si la
suite falla de forma inexplicable después de un despliegue, corre
`php artisan config:clear` primero. Está documentada en `OPTIMIZACION-POS.md`.

---

## Documentación adicional

| Documento | De qué trata |
|---|---|
| [`OPTIMIZACION-POS.md`](OPTIMIZACION-POS.md) | rendimiento del POS: línea base medida, fases de trabajo, hallazgos y qué **no** hacer |
| [`docs/notifications-deployment.md`](docs/notifications-deployment.md) | configuración y publicación del centro de notificaciones |
| [`docs/ui/POS_PANEL_PATTERNS.md`](docs/ui/POS_PANEL_PATTERNS.md) | patrones de los paneles del POS |

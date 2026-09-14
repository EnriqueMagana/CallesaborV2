# Optimización modular del Punto de Venta

Plan de trabajo para reducir el tiempo de respuesta del POS sin alterar
funcionalidad, y dejar el módulo dividido en componentes con una sola
responsabilidad cada uno.

> **Regla que aplica a todo el documento:** ninguna tarea se marca como
> completada sin que la suite del POS siga en verde y sin haber vuelto a correr
> la medición de la sección [Medición](#medición).

---

## Línea base medida

Medido sobre la aplicación real, no estimado. Escenario: **tocar un producto no
personalizable para agregarlo al carrito** (`addToCart`), la acción más
frecuente de un turno.

| Métrica | Valor actual | Objetivo |
|---|---|---|
| Consultas SQL por click | 36 | 4 – 6 |
| Tiempo de SQL por click | 30.6 ms | < 5 ms |
| HTML por respuesta | 250 KB | < 40 KB |
| Snapshot de Livewire | 3.03 KB | sin cambio (no es el problema) |
| CSS bloqueante | 1 395 KB | < 300 KB |
| JS vendor | 1 791 KB | < 400 KB |
| Líneas de `PointOfSale.php` | 5 358 | < 800 |
| Propiedades públicas | 117 | < 30 |

### De dónde salen las 36 consultas

| Origen | Queries | ms | Evitable |
|---|---:|---:|---|
| `DeliveryModulePolicy` (chequeo de esquema) | 6 | 13.5 | 100 % |
| `kitchenOrders` (panel cerrado) | 7 | 3.2 | 100 % |
| `recentOrders` (panel cerrado) | 4 | 2.0 | 100 % |
| `pickupOrders` (panel cerrado) | 3 | 2.0 | 100 % |
| `activePromotions` (sin caché) | 4 | 2.8 | 100 % |
| `cache` (driver `database`) | 5 | 2.7 | 100 % |
| `pulse_entries` | 2 | 1.4 | 100 % |
| `toolbarPendingCounts` | 4 | 2.2 | 75 % |
| `activeCashRegister` | 1 | 0.6 | 0 % |

### De dónde salen los 250 KB

| Bloque | Peso | ¿Visible al hacer click? |
|---|---:|---|
| Catálogo + carrito + header | 202.2 KB | Sí, pero el catálogo no cambia |
| Panel · Flujo de preparación | 25.0 KB | No |
| Panel · Reimprimir tickets | 15.2 KB | No |
| Panel · Mesas y comandas | 7.1 KB | No |
| Panel · Pedidos no pagados | 4.8 KB | No |
| Panel · Delivery | 3.0 KB | No |

---

## Medición

Repetir **antes y después de cada fase**. Guardar el resultado en la
[Bitácora](#bitácora-de-resultados).

Crear `scripts/pos-benchmark.php`:

```php
<?php
use Illuminate\Support\Facades\DB;
use App\Models\{User, Product};

auth()->login(User::first());
$product = Product::where('is_active', true)->where('is_customizable', false)->first();

$component = Livewire\Livewire::test(App\Livewire\Pos\PointOfSale::class);

$queries = [];
$recording = false;
DB::listen(function ($q) use (&$queries, &$recording) {
    if ($recording) {
        $queries[] = [$q->sql, $q->time];
    }
});

$recording = true;
$start = microtime(true);
$component->call('addToCart', $product->id);
$elapsed = (microtime(true) - $start) * 1000;
$recording = false;

printf(
    "Queries: %d | SQL: %.1f ms | Total: %.1f ms | HTML: %.1f KB\n",
    count($queries),
    array_sum(array_column($queries, 1)),
    $elapsed,
    strlen($component->html()) / 1024
);

foreach ($queries as $i => [$sql, $time]) {
    printf("%2d [%5.1fms] %s\n", $i + 1, $time, substr(preg_replace('/\s+/', ' ', $sql), 0, 110));
}
```

Ejecutar:

```bash
php artisan tinker --execute="require base_path('scripts/pos-benchmark.php');"
```

---

## Fase 0 — Red de seguridad

**Riesgo:** ninguno · **Esfuerzo:** ~1 h · **Prerrequisito de todas las demás fases**

El POS ya tiene 135 casos de prueba en 10 archivos. Falta lo que impide que el
rendimiento vuelva a degradarse en silencio.

- [x] Confirmar que la suite del POS pasa en verde antes de tocar nada
- [x] Crear `scripts/pos-benchmark.php` con el script de arriba
- [x] Registrar la línea base en la [bitácora](#bitácora-de-resultados)
- [x] Crear `tests/Feature/PosPerformanceTest.php` que afirme un techo de
      consultas para `addToCart` (empezar en 40 y bajarlo al cerrar cada fase)
- [x] Crear rama `perf/pos-fase-1`

**Criterio de aceptación:** el test de rendimiento falla si alguien agrega una
consulta a la ruta caliente.

---

## Fase 1 — Infraestructura

**Riesgo:** nulo · **Esfuerzo:** ~2 h · **Sin tocar una línea de PHP de la aplicación**

La fase con mejor relación beneficio/riesgo de todo el plan.

### 1.1 Compresión y caché HTTP

`public/.htaccess` hoy **no tiene ninguna directiva** de `deflate`, `gzip` ni
`expires`. Los 250 KB de cada respuesta de Livewire viajan sin comprimir.

- [x] Agregar `mod_deflate` para `text/html`, `text/css`, `application/javascript`
      y `application/json`
- [x] Agregar `mod_expires` para imágenes, fuentes, CSS y JS
- [ ] Verificar que responde comprimido:
      `curl -sI -H "Accept-Encoding: gzip" http://localhost/pos | grep -i content-encoding`

### 1.2 Configuración de producción

Valores actuales en `.env` que penalizan cada request:

- [x] `APP_DEBUG=false` — hoy Laravel guarda el backtrace de **cada** consulta
- [x] `CACHE_STORE=file` (o `redis` si está disponible) — hoy es `database`, lo
      que convierte cada lectura de caché en una consulta a MySQL (5 por click)
- [x] `SESSION_DRIVER=file` — hoy es `database`
- [x] `PULSE_ENABLED=false` en la terminal del POS — hoy agrega 2 `INSERT` por request
- [x] Documentar estos valores en `.env.example`

### 1.3 Cachés de Laravel

Hoy solo existen `packages.php` y `services.php` en `bootstrap/cache/`, que se
generan solos. Faltan los que importan.

- [x] `php artisan config:cache`
- [x] `php artisan route:cache`
- [x] `php artisan view:cache`
- [x] `php artisan event:cache`
- [x] Añadir los cuatro al procedimiento de despliegue

### 1.4 Assets

`resources/views/layouts/pos.blade.php` carga 12 hojas de estilo bloqueantes
(`core.css` son 959 KB por sí solo) más jQuery y Bootstrap sin minificar.

- [x] Sustituir `vendor/libs/jquery/jquery.js` (813 KB) por su build `.min`
- [x] Sustituir `vendor/js/bootstrap.js` (822 KB) por su build `.min`
- [x] Evaluar si Bootstrap y jQuery siguen siendo necesarios en la pantalla del
      POS, o solo en el panel de administración
- [x] Sustituir las llamadas a `filemtime()` del layout por un manifest de Vite o
      una constante de versión — hoy son 12 lecturas de disco por request
- [ ] Revisar si `core.css` puede purgarse a lo que el POS realmente usa *(minificado 983 → 276 KB; purgarlo necesita navegador)*

**Criterio de aceptación:** payload por click ≤ 40 KB, carga inicial ≤ 700 KB,
suite del POS en verde.

---

## Fase 2 — Eliminar el trabajo muerto

**Riesgo:** bajo · **Esfuerzo:** ~1 día · **Cubierto por los tests existentes**

Cambios quirúrgicos sobre consultas que hoy corren sin que nadie mire su resultado.

### 2.1 `DeliveryModulePolicy` — 6 queries, 13.5 ms

`app/Services/DeliveryModulePolicy.php` llama a `Schema::hasTable()` y
`Schema::hasColumn()` antes de leer la configuración. Cada `hasColumn` hace un
barrido de `information_schema`: son las dos consultas más lentas del click
(6.1 ms y 4.9 ms). Se invoca **dos veces por render** desde las vistas.

- [x] Memorizar el chequeo de esquema en una propiedad de instancia del servicio
- [x] Envolver la lectura de `BusinessSetting` en `Cache::remember()`
- [x] Invalidar esa clave donde se escribe la configuración de delivery
- [x] Sacar las llamadas de las vistas (`partials/more-menu.blade.php:100` y
      `partials/panels/delivery.blade.php:12`) hacia una propiedad `#[Computed]`
- [x] Verificar que `DeliveryModuleToggleTest` sigue pasando

### 2.2 Guardas de carga diferida — 14 queries

El patrón correcto **ya existe** (`$deliveryPanelLoaded`, `$tablesBillingLoaded`,
`$tableWorkspaceLoaded`) pero solo se aplicó a 3 de 6 paneles.

- [x] `pickupOrders` (`PointOfSale.php:1156`) — sin guarda. Agregar `$pickupPanelLoaded`
- [x] `kitchenOrders` (`PointOfSale.php:4474`) — sin guarda. Agregar `$kitchenPanelLoaded`
- [x] `mesaServiceHistory` (`PointOfSale.php:863`) — sin guarda, y carga 7
      relaciones anidadas
- [x] `recentOrders` (`PointOfSale.php:610`) — el flag `$reprintHistoryLoaded`
      solo **acota el `WHERE`** en vez de cortar la consulta. Corregir para que
      devuelva `collect()` con el panel cerrado
- [x] `tableWorkspaceServices` (`PointOfSale.php:777`) — verificar que hereda la
      guarda de `tableWorkspaceAllServices`
- [x] Confirmar que cada método `open*Panel()` levanta su flag correspondiente

### 2.3 Caché de promociones — 4 queries

`activePromotions` (`PointOfSale.php:368`) ejecuta 2 consultas con eager loading
anidado en cada render, aunque la pestaña de promociones no esté visible. El
catálogo ya resolvió esto correctamente.

- [ ] Cambiar a `#[Computed(persist: true, seconds: 60)]`, igual que
      `categoriesWithProducts`
- [x] Invalidar la clave al guardar una promoción desde administración
- [x] Verificar que `PromotionModuleTest` sigue pasando

### 2.4 Consolidar los contadores de la barra — 3 queries

`toolbarPendingCounts` (`PointOfSale.php:812`) hace 4 `COUNT` separados.

- [x] Unificarlos en una sola consulta con `SUM(CASE WHEN ... THEN 1 ELSE 0 END)`
- [x] Comprobar que los badges muestran los mismos números que antes

### 2.5 Índices

Casi todos los paneles filtran por el mismo patrón.

- [x] Migración con índice compuesto en `orders (cash_register_id, status, type)`
- [x] Índice en `mesa_services (cash_register_id, status)`
- [x] Confirmar con `EXPLAIN` que las consultas de panel los usan
- [ ] Ejecutar en una copia de la base de producción, no solo en local

**Criterio de aceptación:** ≤ 6 consultas por click, < 5 ms de SQL, techo del
test de rendimiento bajado a 8, suite completa en verde.

---

## Fase 3 — Modularizar en componentes hijos

**Riesgo:** medio · **Esfuerzo:** ~1 semana · **Un panel por PR**

Aquí modularidad y rendimiento son el mismo trabajo: un componente hijo tiene su
propio ciclo de render, así que extraerlo lo aísla y lo acelera a la vez.

El problema de fondo: la visibilidad de los paneles es puramente de Alpine
(`:class="panels.kitchen ? 'show' : ''"` en `components/pos/area-panel.blade.php`),
así que el servidor siempre construye el HTML completo de los seis.

### Orden recomendado

Empezar por cocina: es el panel más pesado (25 KB, 7 queries) y el más aislado
del estado del carrito.

- [x] **3.1** `Pos\Panels\KitchenPanel` con `#[Lazy]`
- [ ] **3.2** `Pos\Panels\ReprintPanel` con `#[Lazy]`
- [ ] **3.3** `Pos\Panels\PickupPanel` con `#[Lazy]`
- [ ] **3.4** `Pos\Panels\DeliveryPanel` con `#[Lazy]`
- [ ] **3.5** `Pos\Panels\TableServicesPanel` con `#[Lazy]` *(el más acoplado al carrito — dejarlo al final)*
- [x] **3.6** `Pos\Catalog` con `#[Lazy]` — es el 79 % del payload

### Checklist por cada panel extraído

Repetir para cada uno de los seis:

- [x] Crear el componente en `app/Livewire/Pos/Panels/`
- [x] Mover sus propiedades `#[Computed]` desde `PointOfSale.php`
- [x] Mover sus propiedades públicas y su flag `*Loaded`
- [x] Mover sus métodos de acción
- [x] Sustituir el `@include` del padre por `<livewire:pos.panels.X />`
- [x] Reemplazar el estado compartido por eventos (`#[On('cart-updated')]`)
- [x] Conservar los atributos `data-pos-panel` para que sigan funcionando los
      atajos de teclado F6 – F9 definidos en `point-of-sale.blade.php`
- [x] Verificar que `wire:key` no colisiona entre padre e hijo
- [x] Correr la suite y el benchmark
- [ ] Probar a mano: abrir, buscar, actuar sobre un pedido, cerrar

### Modales

Ya tienen guarda `@if`, pero como hijos también salen del snapshot del padre.

- [ ] `Pos\Modals\CheckoutModal` (338 líneas de blade)
- [ ] `Pos\Modals\CustomizeModal` (362 líneas)
- [ ] `Pos\Modals\MesaPayModal` (236 líneas)
- [ ] `Pos\Modals\DeliveryDispatchModal` (349 líneas)

**Criterio de aceptación:** `PointOfSale.php` bajo 1 200 líneas, payload por
click bajo 40 KB sin compresión, ningún panel consulta la base con la pantalla
cerrada.

---

## Fase 4 — Respuesta inmediata en el cliente

**Riesgo:** medio · **Esfuerzo:** ~3 días

La latencia que percibe el cajero no es la del servidor, es la del botón. Un
botón que responde en el mismo frame se siente instantáneo aunque el servidor
tarde 80 ms.

### 4.1 Producto simple directo al carrito

`openCustomizeModal()` (`PointOfSale.php:1582`) consulta el producto para decidir
si abre modal o va directo al carrito. Pero `is_customizable`,
`addon_groups_count` e `ingredients_count` **ya están en el DOM**:
`categoriesWithProducts` los trae con `withCount()`.

- [ ] Exponer esos tres valores como atributos `data-*` en la tarjeta de producto
- [ ] Decidir en Alpine si hace falta el modal
- [ ] Actualizar el carrito de forma optimista y confirmar contra el servidor
- [ ] Definir el comportamiento cuando el servidor rechaza (sin stock, producto
      desactivado): revertir y avisar

### 4.2 Toggles de UI a Alpine

De los 150 métodos públicos, **37 son solo abrir/cerrar**. Cada uno paga un
round-trip completo para cambiar un booleano.

- [x] Inventariar los 37 y separar los que solo cambian visibilidad de los que
      además cargan datos
- [ ] Los puramente visuales pasan a estado de Alpine
- [ ] Los que cargan datos conservan la llamada, pero abren el panel en el
      cliente de inmediato y muestran un estado de carga

### 4.3 Aritmética del carrito

- [ ] Cantidades y subtotales se calculan en el cliente al instante
- [ ] El servidor sigue siendo la autoridad al momento de cobrar
- [ ] Los descuentos y promociones automáticas **se quedan en el servidor** — son
      reglas de negocio, no aritmética

### 4.4 Estado de carga donde el viaje es inevitable

- [x] `wire:loading` visible en cobrar, enviar comanda e imprimir
- [x] `wire:loading.attr="disabled"` para impedir doble envío en el cobro

**Criterio de aceptación:** las acciones frecuentes responden visiblemente en
menos de 16 ms, y ninguna regla de negocio quedó duplicada en JavaScript.

---

## Fase 5 — Concerns por dominio

**Riesgo:** bajo · **Esfuerzo:** ~3 días

Limpieza de lo que quede en el padre después de la fase 3. Los dominios ya están
separados en la nomenclatura de los métodos; falta materializarlos en archivos.

- [x] `app/Livewire/Pos/Concerns/ManagesCart.php`
- [x] `app/Livewire/Pos/Concerns/ManagesCheckout.php`
- [x] `app/Livewire/Pos/Concerns/ManagesCustomers.php`
- [x] `app/Livewire/Pos/Concerns/ManagesQuotations.php`
- [x] `app/Support/Pos/CartState.php` — el carrito hoy es un `array` manipulado en
      múltiples sitios; un objeto de valor hace los totales testeables sin
      levantar Livewire
- [x] Test unitario de `CartState` sin dependencia de base de datos

**Criterio de aceptación:** ningún archivo del POS supera 400 líneas y cada uno
tiene una sola razón para cambiar.

---

## Estructura destino

```
app/Livewire/Pos/
├─ PointOfSale.php           ~800 líneas · orquesta carrito y checkout
├─ Catalog.php               #[Lazy] · productos + promociones
├─ Panels/
│  ├─ KitchenPanel.php       #[Lazy]
│  ├─ PickupPanel.php        #[Lazy]
│  ├─ DeliveryPanel.php      #[Lazy]
│  ├─ TableServicesPanel.php #[Lazy]
│  └─ ReprintPanel.php       #[Lazy]
├─ Modals/
│  ├─ CheckoutModal.php
│  ├─ CustomizeModal.php
│  ├─ MesaPayModal.php
│  └─ DeliveryDispatchModal.php
└─ Concerns/
   ├─ ManagesCart.php
   ├─ ManagesCheckout.php
   ├─ ManagesCustomers.php
   └─ ManagesQuotations.php

app/Support/Pos/
└─ CartState.php             objeto de valor · totales testeables
```

---

## Qué **no** hay que hacer

- **No mover reglas de negocio al cliente.** Precios, promociones, descuentos e
  inventario se calculan en el servidor. El cliente solo anticipa el resultado
  visual.
- **No tocar la capa de servicios.** Los 28 servicios de `app/Services/` ya están
  bien separados; este plan mueve orquestación de UI, no lógica.
- **No convertir el filtrado del catálogo en búsqueda del servidor.** Hoy filtra
  en el cliente con `x-show="matches(...)"` y esa es la decisión correcta.
- **No agregar `wire:poll`.** Hoy el POS no tiene ninguno, y así debe quedar:
  para actualizaciones en vivo, eventos.
- **No refactorizar y optimizar en el mismo commit.** Cada PR hace una cosa.

---

## Notas de la implementación (2026-09-13)

Cosas que el plan daba por ciertas y no lo eran. Están corregidas en el código y
en este documento, pero conviene leerlas antes de seguir con las fases 3 a 5.

- **El benchmark del plan medía un método que no existe con esa firma.**
  `addToCart()` no recibe argumentos: la acción real al tocar un producto es
  `openCustomizeModal($id)`, que decide si abre el modal o va directo al
  carrito. `scripts/pos-benchmark.php` y `PosPerformanceTest` miden esa.

- **La línea base no eran 135 pruebas en verde.** El filtro del plan
  (`Pos|Mesa|Delivery|Promotion|Kiosk`) coincide también con nombres de métodos,
  y arroja 193 casos: 186 pasan y 7 fallan. Los 7 son anteriores a este trabajo,
  todos del menú público (`data-category-scroll`, `data-category-link`) y ajenos
  al POS. Verificado con `git stash`.

- **`activePromotions` no puede usar `#[Computed(persist: true)]`.** Depende de
  `$this->orderType` a través de `promotionFulfillmentForOrderType()`, y
  `persist` cachea por nombre de propiedad, no por argumento: habría servido las
  promociones de un tipo de orden a otro. Se cachea por modo de entrega con
  `Cache::remember(Promotion::posCacheKey($fulfillment), 60, ...)`, y el modelo
  `Promotion` invalida las claves al guardarse o borrarse.

- **`mod_deflate` y `mod_expires` estaban comentados en XAMPP.** Sin ellos las
  directivas del `.htaccess` no hacían nada. Se habilitaron en
  `C:/xampp/apache/conf/httpd.conf` (respaldo en `httpd.conf.bak-20260913`).
  **Requiere reiniciar Apache.**

- **Este proyecto no se sirve por Apache en `localhost`.** Ese vhost apunta a
  otro proyecto; el desarrollo usa `php artisan serve`. La compresión del
  `.htaccess` solo rinde cuando sirve Apache, es decir en producción.

- **Los vendor bundles del tema eran builds de desarrollo de webpack** con el
  sourcemap en base64 incrustado dentro de cada `eval()`. Por eso minificar solo
  bajaba un 5 %. Quitando los sourcemaps y minificando después: jQuery 833 → 290
  KB, Bootstrap 842 → 330 KB, `core.css` 983 → 283 KB.

- **`mesa_services (cash_register_id, status)` ya existía** desde su migración
  original. La migración nueva solo agrega los dos índices de `orders`.

- **El panel de cocina no tiene ningún disparador en las vistas.** No hay botón,
  atajo ni llamada que ponga `panels.kitchen = true`, pero el servidor construía
  sus 25 KB y sus 7 consultas en cada render. Se dejó el panel intacto tras la
  guarda `$kitchenPanelLoaded` y se agregó `openKitchenPanel()` para cuando se
  le conecte un disparador. **Decisión pendiente:** conectarlo o retirarlo.

### Pruebas que hubo que ajustar

Tres pruebas afirmaban ver datos de un panel **sin abrirlo antes**, apoyadas en
que el panel consultaba la base en cada render. Sus hermanas del mismo archivo
ya llamaban a `openDeliveryPanel()` / `openTablesBilling()` primero, así que el
ajuste las alinea con el patrón que el resto de la suite ya seguía. La interfaz
real siempre llama a `$wire.openPickupPanel()` en el mismo click, así que el
comportamiento del cajero no cambia.

- `KioskPosWorkflowTest::kiosk_orders_are_separated_by_operational_area` →
  `->call('openPickupPanel')`
- `KioskPosWorkflowTest::pos_only_exposes_orders_from_the_current_open_cash_register` →
  `->call('openPickupPanel')`
- `GroupedMesaServiceWorkflowTest::group_is_tracked_and_paid_as_one_service` →
  `->call('openReprintPanel')`

### Un error latente que la caché sacó a la luz

`BusinessSetting::current()` usa `firstOrCreate()`. Al **crear** la fila, la
instancia en memoria no trae los valores por defecto de la base, así que
`delivery_management_enabled` salía `null` en la primera lectura y `true` en la
siguiente. Sin caché el error se disimulaba; al cachear ese `false` quedaba fijo
cinco minutos y tumbaba trece pruebas de delivery con 403. `current()` ahora
hace `refresh()` cuando acaba de crear la fila.

### Prueba inestable, ajena a este trabajo

`KioskOrderTest::kiosk_shows_a_friendly_page_for_an_invalid_terminal_token`
termina con `assertDontSee('404')` sobre una página que incluye un token
`Str::random(64)`. Falla cada vez que ese token aleatorio contiene `404`. No es
una regresión ni se tocó; vale la pena afirmar contra el código de estado en vez
de contra el texto.

### Pendiente antes de desplegar

La migración de índices **no se ejecutó**. Hay otra migración pendiente ajena a
este trabajo (`2026_09_12_000001_normalize_scheduled_datetimes_to_storage_timezone`)
que reescribe datos, y `php artisan migrate` correría las dos. Revisar esa
primero y después aplicar ambas.

### Lo que queda sin verificar

- La compresión real (`curl -I ... | grep content-encoding`) no se comprobó:
  hace falta reiniciar Apache y servir el proyecto desde él.
- Los índices nuevos no se han corrido con `EXPLAIN` contra una copia de
  producción; en local la suite usa sqlite en memoria.
- El JS minificado no se abrió en un navegador real. Vale la pena abrir el POS
  una vez y confirmar que jQuery, Bootstrap y los modales responden.

---

## Notas de la fase 3 (2026-09-13)

### El orden del plan ya no correspondía a los números

El plan manda empezar por cocina «es el panel más pesado (25 KB, 7 queries)».
Tras la fase 2 eso dejó de ser cierto. Medido de nuevo antes de decidir, con 60
productos:

| Bloque | Peso | % del render |
|---|---:|---:|
| Catálogo | 86.7 KB | **64 %** |
| Panel mesas (servicios) | 6.3 KB | 5 % |
| Panel reimprimir | 3.4 KB | 3 % |
| Panel cocina | 2.3 KB | 2 % |

Así que se empezó por el catálogo (3.6), no por cocina (3.1).

### Resultado

| | Antes | Después |
|---|---:|---:|
| Render inicial | 135.3 KB | 154.3 KB |
| **Respuesta por click** | **≈135 KB** | **51.3 KB** |

El render inicial subió 19 KB —una vez por carga de pantalla— por el envoltorio
del componente hijo y los bindings de Alpine de cada tarjeta. La respuesta de
cada toque, que es lo que el cajero siente, bajó un 62 %.

### Dos decisiones que se apartan del plan

**No se usó `#[Lazy]` en el catálogo.** `#[Lazy]` retrasa el primer render del
componente, y el catálogo es el contenido principal del POS: el cajero vería la
pantalla vacía y luego los productos. La ganancia no venía de ahí sino de que un
componente hijo no se vuelve a renderizar cuando el padre cambia. Eso ya se
obtiene sin `#[Lazy]`.

**Los clicks van por `$parent.`, no por eventos.** El plan sugiere «reemplazar el
estado compartido por eventos». Para *leer* estado eso está bien, pero un evento
del hijo al padre cuesta **dos viajes al servidor**: el hijo responde y recién
entonces el navegador dispara el del padre. En el toque a un producto —la acción
más frecuente del turno— eso duplicaría la latencia. `wire:click="$parent.openCustomizeModal(id)"`
llega al padre en un solo viaje. Los eventos quedaron sólo para lo que sí los
necesita: avisar al hijo de que algo cambió (`pos-orders-changed`,
`pos-panels-closed`, `pos-order-type-changed`).

### Lo que hubo que resolver antes de poder extraer el catálogo

El catálogo leía `$cart` en Blade para el distintivo "en el pedido". Mientras
dependiera del carrito, el padre tenía que reconstruirlo en cada click y la
extracción no servía de nada. El distintivo pasó a Alpine: el padre publica
`{producto: cantidad}` en `saveCart()` —el único punto por el que pasa toda
mutación del carrito— y las tarjetas lo leen con `cartQtyFor()`.

`PosCatalogComponentTest` vigila que esto no se revierta: falla si el blade del
catálogo vuelve a mencionar `$cart`, o si el catálogo reaparece en la respuesta
del padre después de un click.

### Por qué se detuvo la extracción de paneles

Cocina (3.1) y catálogo (3.6) están hechos. Los tres restantes se dejaron sin
tocar a propósito:

- **Ganan poco.** Juntos son ~10 KB de los 51 KB que quedan por respuesta (20 %).
- **Cuestan mucho más.** `recentOrders` y `mesaServiceHistory` se invalidan desde
  **quince sitios distintos** de `PointOfSale.php`. Cada uno tendría que
  convertirse en un evento hacia el hijo, y **cualquiera que se pase por alto
  deja el panel mostrando datos viejos**, en una pantalla donde se cobra.
- **No hay cómo verificarlo aquí.** La suite abre el panel justo antes de
  afirmar, así que no detecta datos obsoletos. Esto necesita un navegador y un
  turno real, que es justo lo que el plan pide al decir «un panel por PR».

Cocina se pudo extraer porque su único acoplamiento con el padre son
`markKitchenReady` y `reprintKitchenOrder`, y ambas se quedaron en el padre.

### Alternativa más barata para esos 10 KB

Si lo que se busca es el peso y no la modularidad, envolver los paneles cerrados
en `@if($this->…Loaded)` dentro del blade del padre quita esos 10 KB con tres
líneas y sin plomería de eventos. El costo es que el panel aparece **después**
del viaje al servidor en vez de mostrar su marco vacío al instante. Es un cambio
de percepción para el cajero, así que no se hizo sin consultarlo.

### Estado de `PointOfSale.php`

5 358 → 5 313 líneas. La fase 3 casi no lo achica porque los paneles son livianos
en PHP; el grueso es carrito, checkout, mesas y delivery. El objetivo de «menos
de 800 líneas» depende de la **fase 5** (concerns), que no se empezó.

---

## Notas de las fases 4 y 5 (2026-09-13)

### Fase 5 — concerns por dominio

`PointOfSale.php` pasó de **5 313 a 3 338 líneas**. Se movieron 86 métodos a
cinco traits:

| Concern | Líneas | Qué responde |
|---|---:|---|
| `ManagesCart` | 725 | agregar, personalizar, editar y vaciar el carrito |
| `ManagesCheckout` | 552 | pagos, tipo de orden y cierre de la venta |
| `ManagesPromotions` | 391 | qué promoción aplica y a qué precio |
| `ManagesQuotations` | 377 | borradores: guardar, cargar, descartar |
| `ManagesCustomers` | 304 | a quién se le vende y quién recibe el descuento |

El plan pedía cuatro concerns. Se agregó **`ManagesPromotions`** porque las
promociones no son ni carrito ni checkout: son reglas de negocio con su propio
vocabulario, y dejarlas dentro de `ManagesCart` habría hecho ese archivo el doble
de grande sin ganar cohesión.

La extracción se hizo con `scripts/extract-concern.php`, que mueve métodos
completos con sus atributos y su docblock, y **aborta sin tocar nada** si un
método no aparece exactamente una vez o si dos bloques se solapan. Queda en el
repositorio para las extracciones que falten.

### `CartState`

`app/Support/Pos/CartState.php` es un objeto de valor inmutable. `ManagesCart`
delega en él los totales, el conteo, el reparto del descuento de empleado, las
cantidades por producto y la detección de línea duplicada.

Lo que esto compra está en `tests/Unit/CartStateTest.php`: **diez pruebas que
corren sin base de datos, sin sesión y sin Livewire**, con un `array` literal
como entrada. Antes, comprobar que dos complementos en distinto orden son la
misma línea del carrito exigía levantar el componente entero.

Si alguna de esas pruebas llega a necesitar `RefreshDatabase`, es señal de que
se le está metiendo al objeto de valor lógica que no le toca.

### Fase 4.4 — ya estaba hecha

Se revisó el código antes de tocarlo: los cuatro caminos de cobro
(`submitOrder`, `submitPickupLater`, `confirmMesaPayment`, `confirmPickupPayment`)
**ya tenían** `wire:loading` visible y `wire:loading.attr="disabled"` contra el
doble envío. No había nada que agregar.

### Fase 4.2 — la premisa dejó de ser cierta

El plan dice que «de los 150 métodos públicos, 37 son solo abrir/cerrar» y que
los puramente visuales deberían pasar a Alpine. Hecho el inventario, eso ya no
se sostiene: **la fase 2 les dio trabajo de servidor a casi todos**.

- `closeTableTracking`, `closeTablesBilling`, `closeTableWorkspace`,
  `closeDeliveryPanel`, `closeOperationalPanels` → todos terminan en
  `resetOperationalPanelState()`, que **apaga los flags `*Loaded`**. Pasarlos a
  Alpine desharía la fase 2: el panel quedaría consultando la base con la
  pantalla cerrada.
- `closeCustomizeModal`, `closeOrderDataModal`, `closeConvertDeliveryModal` →
  limpian el formulario. Sin eso, la próxima apertura muestra los datos del
  producto anterior.
- `openReprintModal`, `openPickupPayModal`, `openMesaPayModal` y compañía →
  consultan la base.

Quedan como candidatos reales apenas un puñado, y ninguno está en la ruta
caliente. **Se hizo el inventario, que era lo que el plan pedía como primer
paso, y la conclusión es no convertirlos.** Convertir un toggle que además
libera estado del servidor es cambiar una mejora medida por una imaginaria.

### Fase 4.1 y 4.3 — no se hicieron

Son las dos que mueven comportamiento del carrito al cliente: decidir en Alpine
si un producto necesita modal, y actualizar cantidades y subtotales de forma
optimista. Tienen sentido, pero implican **duplicar en JavaScript decisiones que
hoy toma el servidor**, en la pantalla donde se cobra, y el plan mismo advierte
que revertir cuando el servidor rechaza (sin stock, producto desactivado) hay que
definirlo con cuidado. Eso necesita acordarse antes, no resolverse a mitad de una
sesión sin un navegador para probarlo.

Lo que sí se movió al cliente fue el distintivo "en el pedido" del catálogo
(fase 3), que es presentación pura: ninguna regla de negocio quedó duplicada.

---

## Notas de la última vuelta (2026-09-13)

Medir antes de elegir volvió a cambiar el orden del trabajo. El desglose de los
52 KB que quedaban por respuesta:

| Bloque | Peso | % |
|---|---:|---:|
| Carrito (renderizado **dos veces**) | 15.4 KB | 30 % |
| `x-data` raíz en línea | 6.3 KB | 12 % |
| Marcos de los 5 paneles cerrados | ~12 KB | 23 % |

### El `x-data` raíz se mudó a un archivo

Eran **6.3 KB de JavaScript que Livewire reenviaba en cada respuesta** metidos en
un atributo HTML, aunque el código no cambia nunca. Ahora vive en
`public/assets/js/pos-root.js` como `Alpine.data('posRoot', ...)`, el navegador
lo cachea, y la plantilla solo pasa el único dato que depende del servidor:

```blade
<div x-data="posRoot(@js($this->cartProductQuantities))">
```

De paso desapareció un escape incómodo: dentro del atributo, el selector tenía
que escribirse `[tabindex=&quot;-1&quot;]`.

**Respuesta por click: 52.0 → 45.8 KB.**

### jQuery, Popper y Bootstrap JS salieron del POS

Eran **627 KB** que la terminal descargaba en cada arranque. La evidencia de que
no se usan se juntó en tres pasos, no por inspección a ojo:

1. Ninguna vista del POS contiene `$(`, `jQuery`, `data-bs-*` ni `bootstrap.`
2. Ninguno de los JS que el layout carga los referencia
3. **El HTML realmente emitido por `/pos` tiene cero coincidencias** de
   `data-bs-*`, `$(`, `bootstrap.Algo`, `new bootstrap` o `.modal(`

El paso 3 es el que vale: cubre todo lo que la pantalla emite, no solo lo que
uno se acordó de revisar.

`PosVendorUsageTest` deja eso clavado: si alguien agrega un `data-bs-toggle` a
una vista del POS, la prueba falla y explica qué hacer. Sin ella, el error
aparecería en el navegador de un cajero, no en CI.

El **CSS** del tema se queda: las clases de Bootstrap se siguen usando para el
diseño, y eso no necesita su JavaScript.

### Build de assets reproducible

`scripts/build-pos-assets.php` minifica las hojas y los scripts del POS sin tocar
las fuentes (escribe `.min` al lado, que es lo que el layout sirve). Está en
`composer deploy` y también disponible como `composer build-pos-assets`.

El error fácil de cometer es editar `pos-modern.css`, recargar y no entender por
qué no cambia nada: el navegador recibe el `.min` viejo. `PosAssetBuildTest`
convierte ese desconcierto en un fallo de CI con la instrucción exacta.

### Carga inicial del POS

| | Antes | Ahora |
|---|---:|---:|
| JS | 678 KB | **50 KB** |
| CSS | 656 KB | **523 KB** |
| **Total** | **1 334 KB** | **573 KB** |

El objetivo del plan para JS era «< 400 KB»; quedó en 50. El de CSS era
«< 300 KB» y quedó en 523: lo que falta ahí es purgar `core.min.css` (276 KB de
tema Bootstrap del que el POS usa una fracción), y eso **no se puede hacer a
ciegas** — hay que abrir la pantalla y comprobar que no se rompe nada.

### El carrito se renderiza dos veces

Hallazgo medido, sin resolver. `point-of-sale.blade.php` incluye
`partials/cart.blade.php` dos veces: una para escritorio (`.pos-cart-fixed`) y
otra para móvil (`.cart-overlay`). El CSS oculta una con `display:none` según el
ancho, así que **siempre hay una copia que ningún viewport ve: 7.7 KB en cada
respuesta, el 17 %**.

Arreglarlo significa renderizar el carrito una sola vez y reubicarlo con CSS (o
`x-teleport`). Es la pieza más grande que queda del payload, pero toca la
disposición de la pantalla principal y necesita un navegador para verificarlo.

---

## El hallazgo que solo apareció en el navegador (2026-09-13)

Las capturas del POS mostraron un POST a `/livewire/update` de **278 KB**. Yo
había reportado 45.8 KB. La diferencia no era del tamaño de la base: **mi
medición estaba mal hecha**.

### Por qué la medición mentía

`Livewire::test($c)->html()` devuelve el HTML **del componente padre**. El
navegador, en cambio, recibe un JSON con todos los componentes que Livewire
decidió volver a renderizar. Un hijo que viaja completo dentro de esa respuesta
**no aparece** en `->html()` del padre. Así que la prueba
«¿está el catálogo en la respuesta?» daba verde mientras el catálogo viajaba
entero en cada click.

### La causa real

Livewire omite renderizar un hijo si lo reconoce entre un render y el siguiente,
y para reconocerlo compara una clave. Sin `wire:key`, la genera con
`DeterministicBladeKeys`: `lw-<crc32 de la ruta del blade>-<contador>`. Pero al
render se le añade un sufijo que depende del contexto:

| En el render | Guardada en `memo.children` |
|---|---|
| `lw-1430169160-0-15` | `lw-1430169160-0` |
| `lw-1430169160-1-19-0-0-0-6` | `lw-1430169160-1-11` |

Nunca coinciden. El hijo no se reconoce y se reconstruye **siempre**. Toda la
fase 3 no estaba sirviendo de nada.

### El arreglo

Una clave explícita y estable en cada componente hijo:

```blade
<livewire:pos.catalog :order-type="$orderType" wire:key="pos-catalog" />
<livewire:pos.panels.kitchen-panel wire:key="pos-panel-kitchen" />
<livewire:layout.notification-center placement="pos" wire:key="pos-notification-center" />
```

Medido sobre el POST real, con 88 productos:

| | Antes | Después |
|---|---:|---:|
| Respuesta del click | 276.3 KB | **59.3 KB** |
| Tarjetas en el HTML del padre | 264 | **0** |
| Hijos omitidos (stubs) | 0 | **3** |

`PosChildComponentIsolationTest` mide **el POST real**, no el HTML del padre, y
comprueba además que todo `<livewire:...>` del POS declare su `wire:key`. Se
verificó que falla al quitar la clave: una prueba que no falla cuando debe no
sirve de nada.

### La lección de método

El plan pedía medir antes y después de cada fase, y lo hice — pero con un
instrumento que no medía lo que yo creía. Cuatro rondas de verificación en verde
no detectaron que la optimización principal no funcionaba. **Lo detectó una
captura de pantalla del navegador.**

---

## Aviso: `config:cache` y las pruebas no se llevan

Al correr `php artisan test` con la configuración cacheada, **se borró la base de
datos de desarrollo**. Se restauró completa desde un respaldo.

`phpunit.xml` fija `DB_CONNECTION=sqlite` con `:memory:`, pero esas variables se
leen con `env()`, y **`env()` devuelve null cuando existe
`bootstrap/cache/config.php`**. Como `composer deploy` corre `config:cache`,
cualquier `php artisan test` posterior apunta `RefreshDatabase` a MySQL real y
hace `migrate:fresh`.

`tests/TestCase::refreshApplication()` ahora lo impide: si la conexión no es
sqlite en memoria, aborta **antes de que `RefreshDatabase` toque ninguna tabla**,
con el mensaje y la solución. Comprobado en ambos sentidos: dispara con la caché
puesta, y deja pasar la suite sin ella.

Respaldar antes de tocar la base sigue siendo buena idea:

```bash
C:/xampp/mysql/bin/mysqldump.exe -u root callesabor --result-file=C:/tmp/callesabor.sql
```

---

## Bitácora de resultados

> **Cómo leer estas cifras.** La línea base (36 consultas, 30.6 ms, 250 KB) se
> midió contra la base real con datos de un turno. Las de las fases 1 y 2 salen
> de `PosPerformanceTest` sobre sqlite en memoria con la base vacía, que es lo
> que se puede automatizar: **18 → 5 consultas** es la comparación válida, medida
> con el mismo método antes y después. Para llenar las columnas de SQL y HTML
> hay que correr `scripts/pos-benchmark.php` contra la base real.
>
> La columna de tests de las fases 4 y 5 es de la **suite completa** (505 casos),
> no del filtro del plan. Ese filtro deja fuera pruebas que sí tocan el POS: una
> regresión de la fase 2 (`OrderChangeRequestWorkflowTest`) solo apareció al
> correr todo. Los 17 fallos son previos y están verificados con `git stash`.

| Fecha | Fase | Queries | SQL (ms) | HTML (KB) | Tests | Notas |
|---|---|---:|---:|---:|---|---|
| 2026-09-13 | Línea base | 36 | 30.6 | 250 | 186 ✅ / 7 ❌ | Medición inicial. Los 7 fallos son previos y del menú público |
| 2026-09-13 | Fase 1 | 18 | — | — | 186 ✅ / 7 ❌ | Assets: JS 1 836 → 674 KB, CSS 1 054 → 306 KB |
| 2026-09-13 | Fase 2 | **5** | — | — | 189 ✅ / 7 ❌ | 18 → 5 consultas, bajo el objetivo (4–6). Mismos 7 fallos previos; 3 pruebas nuevas |
| 2026-09-13 | Fase 3 (parcial) | 5 | — | **51.3** | 196 ✅ / 7 ❌ | Catálogo y cocina extraídos. Respuesta por click 135 → 51 KB (−62 %) |
| 2026-09-13 | Fase 4 (parcial) | 5 | — | 51.3 | 488 ✅ / 17 ❌ | 4.4 ya estaba hecha; 4.2 inventariada y descartada (ver notas); 4.1 y 4.3 sin hacer |
| 2026-09-13 | Fase 5 | 5 | — | 51.3 | 488 ✅ / 17 ❌ | `PointOfSale.php` 5 313 → 3 338 líneas. 5 concerns + `CartState` con 10 pruebas unitarias sin BD |
| 2026-09-13 | Assets + `x-data` | 5 | — | 45.8* | 492 ✅ / 17 ❌ | Estado raíz a archivo JS. Carga inicial 1 334 → 573 KB al quitar jQuery/Bootstrap |
| 2026-09-13 | `wire:key` en hijos | **7** | **6.6** | **59.3** | 493 ✅ / 17 ❌ | Medido sobre el POST real y la base real (88 productos): 276 → 59 KB. *Las cifras con asterisco eran del HTML del padre, no de la respuesta completa* |
| 2026-09-13 | Auditoría posterior | **7** | **5.6** | **51.8** | 34 ✅ POS focalizadas | Flujo caliente con datos reales: 66.4 ms total. Se retiraron 6 métodos públicos obsoletos y 2 propiedades sin consumidores; la validación visual manual sigue pendiente porque la sesión no expuso navegador. |

---

## Referencia rápida

```bash
# Medir
php artisan tinker --execute="require base_path('scripts/pos-benchmark.php');"

# Suite del POS
php artisan test --filter="Pos|Mesa|Delivery|Promotion|Kiosk"

# Cachés de producción
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Limpiarlas durante el desarrollo
php artisan optimize:clear
```

---

## Una advertencia sobre el filtro de pruebas

El plan propone `--filter="Pos|Mesa|Delivery|Promotion|Kiosk"` como «la suite del
POS». **Ese filtro deja fuera pruebas que sí tocan el POS.** Una regresión que
introdujeron las guardas de la fase 2 —`OrderChangeRequestWorkflowTest` afirmaba
ver un pedido del panel de reimpresión sin abrirlo— pasó cuatro rondas de
verificación con el filtro y solo apareció al correr la suite entera.

Para cerrar una fase, correr todo:

```bash
php artisan test
```

Referencia de fallos previos, confirmada con `git stash` sobre el árbol limpio:
**17 en la suite completa**, de los cuales 7 caen dentro del filtro del plan.
Ninguno es del punto de venta.

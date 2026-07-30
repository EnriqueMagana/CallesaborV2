# Patrones de paneles operativos del POS

## Seguimiento de mesas con listas extensas

### Shell reutilizable

Todos los paneles operativos del POS deben renderizarse mediante
`resources/views/components/pos/area-panel.blade.php`. El componente garantiza:

- Superficie flotante consistente en escritorio y pantalla completa en móvil.
- Encabezado, herramientas y navegación fuera de la región desplazable.
- `pos-area-panel__body` como único propietario del scroll.
- Cierre accesible, área táctil mínima de 44 px y compatibilidad con modo oscuro.
- Slots para navegación, herramientas, estado de carga, cuerpo personalizado y pie.

No se deben crear nuevos paneles copiando manualmente overlay, backdrop, header y body.

### Incidente

El panel `pos-tracking-panel` renderizaba todos los servicios y todas sus comandas expandidas. Con muchas mesas abiertas, el contenido útil quedaba fuera del área visible y el usuario podía interpretar que faltaban pedidos.

El problema no era solamente la ausencia aparente de scroll. La combinación de una lista dinámica sin límite visual, tarjetas altas y todos los detalles abiertos producía una densidad vertical que no escalaba.

### Patrón obligatorio

- El encabezado, las herramientas y el botón de cierre permanecen fuera del área desplazable.
- `pos-area-panel__body` es el único propietario del scroll vertical y debe usar `min-height: 0`, `overflow-y: auto` y una altura del panel limitada por `100dvh`.
- Cada servicio se representa con un resumen compacto siempre visible.
- Las comandas utilizan divulgación progresiva mediante acordeón.
- Solo un servicio puede estar expandido al mismo tiempo.
- El primer servicio se abre por defecto para evitar un panel aparentemente vacío.
- El resumen cerrado conserva nombre del servicio, hora, duración y contadores de estados.
- El control del acordeón debe ser un `button`, exponer `aria-expanded` y enlazar su región con `aria-controls`.
- Los objetivos táctiles deben medir al menos 44 × 44 px.
- Modo claro y oscuro deben conservar contraste en bordes, foco, estados y superficies.
- Las transiciones se limitan a opacidad o transformación y deben respetar `prefers-reduced-motion`.

### Estados de carga

El skeleton y el contenido real son mutuamente excluyentes:

- Skeleton: `wire:loading`.
- Contenido o estado vacío: `wire:loading.remove`.

Nunca debe mostrarse el estado vacío debajo del skeleton.

El skeleton completo se reserva para la primera carga de datos. Acciones sobre contenido ya visible —actualizar,
cambiar estado, reimprimir o cobrar— deben conservar la lista montada y mostrar progreso únicamente en el botón
accionado mediante un `wire:target` específico. No se debe sustituir todo el panel por un skeleton durante una
mutación localizada.

### Veracidad financiera en cuentas divididas

- Cuando existe una separación activa, su saldo pendiente es la única cifra cobrable.
- La vista no debe repetir debajo el total original de la orden, porque ya no representa el saldo actual.
- Las cuentas pagadas pueden conservarse como referencia dentro de la separación, pero nunca sumarse otra vez.
- Una subcuenta pendiente en cero se presenta como “sin consumo” y no como una orden activa con el importe original.
- El carrito se limpia solamente después de confirmar que un pedido guardado fue persistido correctamente.

El modal, el resumen y la validación del pago deben obtener el total desde el mismo servicio operativo. Nunca se
debe mostrar el total consolidado y validar después contra las órdenes de una sola mesa física.

### Personalización con muchos ingredientes

- La cuadrícula usa tarjetas de ancho mínimo legible y cambia de varias columnas a una columna según el contenedor.
- Imagen, descripción y controles tienen áreas de layout independientes; no deben competir por el mismo ancho.
- El cuerpo del modal conserva el scroll. No se agrega un segundo scroll dentro de la cuadrícula.
- Búsqueda, cantidades, límites y total se resuelven localmente. Se realiza una sola petición al confirmar.
- Cada botón de cantidad conserva un objetivo táctil mínimo de 40–44 px y un foco visible.
- Las imágenes reservan dimensiones y usan carga diferida para evitar saltos de layout.

### Antipatrones prohibidos

- Renderizar todas las tarjetas operativas abiertas por defecto.
- Crear scroll vertical dentro de cada servicio o comanda.
- Permitir que encabezados fijos reduzcan la lista sin asignar `min-height: 0` al cuerpo flexible.
- Ocultar comandas únicamente con una altura fija o `overflow: hidden`.
- Usar un `div` no interactivo como control del acordeón.
- Depender solo del color para comunicar estados.

### Matriz mínima de verificación

Antes de entregar cambios en paneles operativos, verificar:

1. 0, 1, 10 y 30 servicios.
2. Servicios con 0, 1 y múltiples comandas.
3. Resoluciones de 375 × 667, 768 × 1024 y 1440 × 900.
4. Orientación vertical y horizontal.
5. Modo claro y modo oscuro.
6. Navegación por teclado y foco visible.
7. Skeleton sin contenido o estado vacío simultáneo.
8. Último servicio y última comanda alcanzables mediante scroll.

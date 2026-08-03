# Calle Sabor — sistema visual público

## Dirección

- Producto: experiencia pública de restaurante con portada, reservaciones y menú informativo, sin pedidos.
- Enfoque: content-first, cálido, profesional y mobile-first.
- Superficie base: blanco; el verde configurable se usa en acciones, foco, estado abierto y acentos.
- Estilo: food-app suave inspirado en la claridad de Rappi y DiDi Food, con tarjetas limpias, radios amplios y sombras discretas.

## Tokens

- `--menu-primary`: configurable desde el panel; valor inicial `#15803d`.
- Texto principal: `#17211b`; secundario: `#607066`.
- Fondo secundario: `#f4f8f5`; borde: `#dfe8e1`.
- Tipografía única: `Poppins` en títulos, controles, navegación y cuerpo, usando peso y escala para la jerarquía.
- Ritmo espacial: múltiplos de 4/8 px; contenedor máximo de 1200 px.
- Movimiento: 180–300 ms, únicamente con opacidad y transformación.

## Patrones

- Portada en dos niveles: banner superior con nombre/estado y ficha inferior solapada con logo, dirección y acceso al menú.
- Navegación sticky y desplazable en móvil.
- Productos destacados en carril táctil; catálogo completo en rejilla por categorías.
- Toda tarjeta de producto abre un modal informativo con descripción, precio, ingredientes y complementos.
- Reservación mediante una sola acción en la portada que abre un wizard modal de tres pasos: fecha, hora/personas y datos de contacto.
- En escritorio el wizard es un modal centrado; en móvil es una hoja inferior con zona segura.
- La dirección completa genera un enlace de búsqueda en Google Maps cuando no hay un enlace personalizado.

## Accesibilidad y rendimiento

- Contraste AA, foco visible, trampa de foco en modal y objetivos táctiles de al menos 44 px.
- Formularios con etiquetas visibles, errores cercanos y controles semánticos.
- Imágenes con dimensiones, carga diferida y texto alternativo.
- Sin dependencia exclusiva del color para estados.
- `prefers-reduced-motion` elimina animaciones y transiciones.
- Puntos de control obligatorios: 375, 768, 1024 y 1440 px.

## Evitar

- Botones o lenguaje que sugieran pedidos.
- Carruseles automáticos, emojis estructurales o animación decorativa.
- Verdes fluorescentes, fondos oscuros dominantes y texto sobre imagen sin degradado.
- Formularios completos visibles en la portada o múltiples acciones primarias dentro del mismo paso.

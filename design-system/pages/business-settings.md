# Configuración del negocio — reglas específicas

Estas reglas complementan `design-system/MASTER.md` para el panel administrativo.

## Dirección visual

- Superficies blancas, bordes violeta-gris suaves y elevación discreta.
- Los estados deben combinar icono, texto y color; nunca depender únicamente del color.
- Los controles táctiles deben medir al menos 44 px.
- Los grupos complejos deben ofrecer resumen, acciones rápidas y ayuda contextual.

## Destacados

- Una estrella dorada rellena aparece únicamente en productos seleccionados.
- Un producto no seleccionado conserva el espacio de estado, pero no muestra ninguna estrella.
- El estado seleccionado también cambia borde y superficie para ser perceptible sin depender del icono.

## Galería

- Hasta 24 imágenes en una cuadrícula adaptable.
- El skeleton pertenece al fondo reservado de la imagen; no depende de eventos JavaScript que puedan quedar bloqueados.
- Cada fotografía incluye pie editable, eliminación visible y estado pendiente.
- Durante la transferencia se deshabilitan las acciones que podrían interrumpirla.

## Horarios

- Resumen de días abiertos y presets para semana laboral, todos los días o cierre completo.
- Cada día muestra nombre, estado textual, interruptor y rango horario.
- Etiquetas explícitas, foco visible y disposición de una columna en móvil.

## Constructor de menú

- La previsualización de imagen reserva espacio y muestra skeleton desde el inicio de la transferencia.
- Formatos admitidos: JPG, PNG, GIF y WebP, hasta 6 MB.
- Los catálogos auxiliares se conservan durante cinco minutos por instancia Livewire y se invalidan después de una mutación.
- Evitar consultas dentro de bucles; usar conteos precargados.

# Portada pública

- Propósito: la raíz `/` es la recepción digital del restaurante; el catálogo vive en `/menu`.
- Tipografía: Poppins en toda la experiencia pública.
- Jerarquía: banner, accesos principales, categorías, acción de reserva, galería, horarios, contacto y llamada final al menú.
- Reserva: una sola llamada a la acción abre un wizard de tres pasos dentro de un modal accesible.
- Escritorio: modal centrado, ancho máximo de 760 px y resumen visible antes de enviar.
- Móvil: hoja inferior, controles de 48 px, progreso por iconos y botones fijos fuera del área desplazable.
- Galería: carrusel manual, sin reproducción automática, operable con botones y teclado.
- Movimiento: transiciones suaves mediante CSS; no se incorpora GSAP/AnimeJS para evitar peso innecesario.
- Estado: una solicitud pública se registra como `pendiente` y aparece en el calendario con hora, nombre y personas.

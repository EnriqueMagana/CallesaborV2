<?php

namespace App\Support;

final class PermissionCatalog
{
    /**
     * Permissions owned by the application, grouped by the module where they act.
     * The description is intentionally operational: it is shown when assigning roles.
     *
     * @return array<string, array<string, string>>
     */
    public static function definitions(): array
    {
        return [
            'punto_venta' => [
                'crear ventas en punto de venta' => 'Permite confirmar desde el POS una venta nueva, generar la orden y registrar sus productos y pago inicial.',
                'gestionar borradores en punto de venta' => 'Permite guardar, abrir, actualizar y eliminar pedidos guardados o borradores creados desde el POS.',
                'ver pedidos en punto de venta' => 'Permite abrir en el POS los paneles operativos de pedidos por cobrar, cocina, mesas y delivery; no permite cambiar estados.',
                'iniciar preparacion en punto de venta' => 'Permite cambiar en el POS una orden de Pendiente a En preparación y emitir su comanda inicial de cocina.',
                'marcar pedidos listos en punto de venta' => 'Permite cambiar en el POS una orden de En preparación a Lista para cobro o entrega.',
                'cobrar pedidos en punto de venta' => 'Permite cobrar desde el POS pedidos listos de ventanilla, recoger, kiosco o delivery y registrar sus formas de pago.',
                'convertir pedidos a delivery en punto de venta' => 'Permite convertir desde el POS un pedido activo de ventanilla o recoger en un pedido de delivery.',
                'usar punto de venta' => 'Permite abrir el POS, consultar el catálogo y preparar una venta; las acciones de cobro, mesas y caja requieren sus permisos propios.',
                'ver menu' => 'Permite consultar el catálogo del punto de venta para armar la venta y validar disponibilidad antes de cobrar.',
                'ver clientes' => 'Permite buscar clientes y revisar sus datos básicos desde el punto de venta antes de registrar una venta.',
                'crear clientes' => 'Permite registrar clientes nuevos directamente desde el punto de venta para completar la operación.',
                'ver ordenes' => 'Permite consultar pedidos activos, cerrados y su estado actual desde el punto de venta.',
                'crear ordenes' => 'Permite generar una venta o pedido desde el punto de venta.',
                'editar ordenes' => 'Permite modificar artículos, cantidades y datos de una orden antes de cerrarla desde el punto de venta.',
                'cerrar ordenes' => 'Permite finalizar una venta y dejar la orden lista para cobro o cierre desde el POS.',
                'reimprimir tickets' => 'Permite volver a imprimir tickets de cliente y comandas desde el punto de venta.',
                'ver caja' => 'Permite consultar el turno, los movimientos y el estado de la caja desde el punto de venta.',
                'abrir caja' => 'Permite iniciar la caja y dejarla disponible para ventas desde el punto de venta.',
                'cerrar caja' => 'Permite cerrar el turno de caja y registrar el corte final desde el punto de venta.',
                'aplicar descuentos' => 'Permite aplicar descuentos manuales durante el cobro de una venta.',
                'registrar movimientos de caja' => 'Permite registrar ingresos y salidas de efectivo durante la operación del punto de venta.',
                'ver mesas' => 'Permite consultar el mapa de mesas y las cuentas activas para atender servicio desde el punto de venta.',
                'asignar mesas' => 'Permite tomar una mesa disponible y abrir un servicio desde el punto de venta.',
                'ordenar mesas' => 'Permite crear y agregar productos a una cuenta de mesa desde el punto de venta.',
                'cerrar mesas' => 'Permite solicitar el cierre de una cuenta de mesa y dejarla lista para cobro.',
                'cobrar mesas' => 'Permite registrar el pago de una cuenta de mesa desde el punto de venta.',
                'registrar salida de insumos' => 'Permite descontar insumos por merma, consumo interno o traslado mientras se opera el punto de venta.',
            ],
            'usuarios' => [
                'ver usuarios' => 'Permite consultar la lista de usuarios, sus datos básicos, estado y roles asignados.',
                'crear usuarios' => 'Permite registrar cuentas nuevas de personal y asignarles un rol inicial.',
                'editar usuarios' => 'Permite modificar los datos, sucursal y configuración de una cuenta existente.',
                'eliminar usuarios' => 'Permite eliminar cuentas de usuario que ya no deben existir en el sistema.',
                'bloquear usuarios' => 'Permite bloquear o reactivar el acceso de una cuenta sin eliminarla.',
                'gestionar roles' => 'Permite crear, editar y eliminar roles, y cambiar el conjunto de permisos asignado a cada rol.',
                'gestionar permisos' => 'Permite crear, editar y eliminar permisos del catálogo global; puede cambiar las capacidades disponibles para todos los roles.',
            ],
            'clientes' => [
                'ver clientes' => 'Permite consultar el directorio, historial y datos de contacto de los clientes.',
                'crear clientes' => 'Permite registrar clientes nuevos desde administración o durante una venta.',
                'editar clientes' => 'Permite actualizar los datos de contacto y la información de clientes existentes.',
                'eliminar clientes' => 'Permite eliminar registros de clientes del directorio.',
            ],
            'menu' => [
                'ver menu' => 'Permite consultar el catálogo administrativo de productos, categorías, ingredientes y complementos.',
                'crear platos' => 'Permite crear productos del menú, definir precio, descripción, imagen y disponibilidad.',
                'editar platos' => 'Permite modificar productos existentes, incluidos precio, receta, imagen y disponibilidad.',
                'eliminar platos' => 'Permite eliminar productos del menú y dejar de ofrecerlos en los canales de venta.',
                'gestionar categorias' => 'Permite crear, editar, ordenar y eliminar categorías utilizadas para organizar el menú.',
                'gestionar complementos' => 'Permite crear, editar y eliminar ingredientes, extras y opciones de personalización.',
                'gestionar areas impresion' => 'Permite configurar las áreas de preparación a las que se envían comandas, como cocina o barra.',
                'gestionar menu digital' => 'Permite cambiar la configuración y publicación del menú digital para clientes.',
            ],
            'promociones' => [
                'ver promociones' => 'Permite consultar promociones, vigencias, condiciones, productos y canales donde aplican.',
                'crear promociones' => 'Permite crear promociones y definir sus descuentos, condiciones, horarios y alcance.',
                'editar promociones' => 'Permite modificar o activar promociones existentes y cambiar sus condiciones.',
                'eliminar promociones' => 'Permite eliminar promociones del sistema y detener su aplicación.',
            ],
            'ordenes' => [
                'ver ordenes' => 'Permite consultar pedidos, sus productos, importes, cliente, canal y estado actual.',
                'crear ordenes' => 'Permite crear pedidos y guardar ventas pendientes desde los canales autorizados.',
                'solicitar cancelacion de ordenes' => 'Permite enviar al owner y al super-admin una solicitud justificada para cancelar una orden activa; no cancela la orden por sí solo.',
                'solicitar modificacion de ordenes' => 'Permite proponer productos por agregar, retirar o cambiar de cantidad y enviar el nuevo total al owner y al super-admin; no modifica la orden hasta su aprobación.',
                'revisar solicitudes de ordenes' => 'Permite abrir la bandeja exclusiva y aprobar o rechazar solicitudes de cancelación y modificación; al aprobar aplica el cambio y está reservado al owner y al super-admin.',
                'editar ordenes' => 'Permite cambiar productos, cantidades, cliente y datos de pedidos que aún admiten edición.',
                'cerrar ordenes' => 'Permite cobrar y finalizar pedidos, registrar sus pagos y cambiar su estado a completado.',
                'reimprimir tickets' => 'Permite volver a imprimir tickets de cliente y comandas de cocina de órdenes existentes.',
            ],
            'mesas' => [
                'ver mesas' => 'Permite consultar el mapa de mesas y las cuentas visibles para el usuario.',
                'ver todas las mesas' => 'Permite consultar mesas y cuentas asignadas a cualquier integrante del personal.',
                'asignar mesas' => 'Permite tomar una mesa disponible y asignársela al usuario actual.',
                'ordenar mesas' => 'Permite abrir y agregar productos a órdenes vinculadas con una mesa.',
                'cerrar mesas' => 'Permite solicitar el cierre de una cuenta de mesa para que quede lista para cobro.',
                'liberar mesas' => 'Permite desasignar y liberar una mesa cuando su servicio puede terminar.',
                'reasignar mesas' => 'Permite transferir una mesa y su servicio activo a otro integrante del personal.',
                'cobrar mesas' => 'Permite registrar el pago de una cuenta de mesa y concluir el servicio.',
                'dividir mesas' => 'Permite dividir una cuenta de mesa en subcuentas para cobrar por separado.',
                'gestionar mesas' => 'Permite administrar la estructura operativa de mesas y áreas del salón.',
                'gestionar grupos' => 'Permite unir o separar mesas para atenderlas como un mismo grupo.',
                'crear areas de mesas' => 'Permite crear áreas del salón para organizar las mesas.',
                'editar areas de mesas' => 'Permite cambiar el nombre, orden y configuración de las áreas del salón.',
                'eliminar areas de mesas' => 'Permite eliminar áreas del salón que ya no se utilizan.',
                'crear mesas' => 'Permite agregar mesas nuevas al mapa del salón.',
                'editar mesas' => 'Permite modificar nombre, capacidad, ubicación y configuración de mesas existentes.',
                'eliminar mesas' => 'Permite eliminar mesas del mapa del salón.',
                'cambiar estado mesas' => 'Permite cambiar manualmente el estado operativo de una mesa.',
                'cancelar divisiones mesas' => 'Permite revertir una división de cuenta y volver a reunir sus subcuentas.',
            ],
            'caja' => [
                'ver caja' => 'Permite consultar la caja actual, sus movimientos, totales y estado de apertura.',
                'abrir caja' => 'Permite iniciar un turno de caja indicando el fondo inicial.',
                'cerrar caja' => 'Permite realizar el corte y cerrar una caja, capturando los importes contabilizados.',
                'aplicar descuentos' => 'Permite aplicar descuentos manuales durante el cobro de una orden.',
                'anular pagos' => 'Permite anular pagos ya registrados y recalcular el saldo de la orden.',
                'registrar gastos' => 'Permite registrar salidas de caja por gastos; no autoriza registrar ingresos de efectivo.',
                'registrar movimientos de caja' => 'Permite registrar ingresos de efectivo y salidas de caja en el turno abierto.',
            ],
            'reportes' => [
                'ver reportes' => 'Permite consultar reportes operativos generales disponibles en el sistema.',
                'exportar reportes' => 'Permite descargar o exportar la información de los reportes.',
                'ver reportes financieros' => 'Permite consultar reportes con ventas, costos, utilidad, caja y otros importes financieros sensibles.',
            ],
            'configuracion' => [
                'ver configuracion' => 'Permite consultar la configuración del sistema sin modificarla.',
                'editar configuracion' => 'Permite modificar parámetros generales de funcionamiento del sistema.',
                'gestionar configuracion negocio' => 'Permite cambiar datos del negocio, identidad, contacto y preferencias comerciales.',
                'ver menu sidebar' => 'Permite consultar la configuración de opciones del menú lateral administrativo.',
                'crear menu sidebar' => 'Permite crear accesos nuevos en el menú lateral administrativo.',
                'editar menu sidebar' => 'Permite modificar nombre, icono, ruta, orden y reglas de accesos del menú lateral.',
                'eliminar menu sidebar' => 'Permite eliminar accesos del menú lateral administrativo.',
                'gestionar bloqueos por caja' => 'Permite decidir qué módulos requieren una caja abierta para poder utilizarse.',
            ],
            'kiosco' => [
                'gestionar kioscos' => 'Permite configurar, activar y administrar terminales de autoservicio.',
            ],
            'delivery' => [
                'ver delivery' => 'Permite consultar pedidos de entrega y su información logística.',
                'tomar delivery' => 'Permite asignarse un pedido de entrega disponible.',
                'entregar delivery' => 'Permite marcar como entregado un pedido asignado al usuario.',
                'gestionar delivery' => 'Permite administrar todos los pedidos de entrega, sus asignaciones y estados.',
            ],
            'reservas' => [
                'ver reservas' => 'Permite consultar el calendario y los datos de las reservaciones.',
                'crear reservas' => 'Permite registrar reservaciones nuevas para clientes.',
                'editar reservas' => 'Permite modificar fecha, horario, personas y datos de una reservación.',
                'cambiar estado reservas' => 'Permite confirmar, atender o actualizar el estado operativo de una reservación.',
                'cancelar reservas' => 'Permite cancelar reservaciones existentes.',
            ],
            'inventario' => [
                'ver inventario' => 'Permite consultar existencias, unidades, mínimos y movimientos de inventario.',
                'gestionar insumos' => 'Permite crear, editar y administrar los insumos controlados en inventario.',
                'ajustar inventario' => 'Permite aumentar o disminuir existencias manualmente y registrar el motivo del ajuste.',
                'generar compras inventario' => 'Permite crear órdenes de compra para reabastecer insumos.',
                'editar compras inventario' => 'Permite modificar órdenes de compra que todavía admiten cambios.',
                'eliminar compras inventario' => 'Permite eliminar órdenes de compra del inventario.',
                'recepcionar compras inventario' => 'Permite recibir compras y sumar las cantidades recibidas a las existencias.',
                'registrar salida de insumos' => 'Permite descontar insumos desde el POS por merma, consumo interno o traslado, dejando movimiento de auditoría.',
            ],
            'desarrollo' => [
                'ver panel super admin' => 'Permite acceder al panel técnico de superadministración y sus herramientas internas.',
                'ejecutar diagnosticos' => 'Permite ejecutar diagnósticos técnicos del sistema y consultar sus resultados.',
                'probar notificaciones' => 'Permite enviar notificaciones de prueba desde las herramientas técnicas.',
            ],
        ];
    }

    public static function description(string $permission): ?string
    {
        foreach (self::definitions() as $permissions) {
            if (isset($permissions[$permission])) {
                return $permissions[$permission];
            }
        }

        return null;
    }
}

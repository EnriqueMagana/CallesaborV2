<?php

namespace App\Support;

class NotificationEventCatalog
{
    /**
     * The permission list is an OR condition: possessing any listed permission
     * is enough to receive the event. Route authorization remains authoritative.
     */
    public static function definitions(): array
    {
        return [
            'tables' => [
                'label' => 'Mesas',
                'icon' => 'bx-table',
                'description' => 'Pedidos creados por meseros y avisos cuando cocina los termina.',
                'events' => [
                    'table.order_created' => self::event('Nuevo pedido de mesa', 'Avisa cuando se envía a cocina un pedido nuevo desde una mesa.', ['ver mesas', 'ver pedidos en punto de venta'], 'bx-dish'),
                    'table.order_ready' => self::event('Pedido de mesa listo', 'Avisa al personal responsable cuando cocina termina un pedido de mesa.', ['ver mesas', 'ver pedidos en punto de venta'], 'bx-check-circle'),
                    'table.help_requested' => self::event('Solicitud de apoyo en mesa', 'Avisa a un mesero cuando otro integrante solicita su ayuda en una mesa o grupo.', ['ver mesas'], 'bx-user-plus'),
                    'table.help_accepted' => self::event('Apoyo aceptado', 'Confirma al solicitante que el mesero invitado se incorporó al servicio.', ['ver mesas'], 'bx-user-check'),
                    'table.help_declined' => self::event('Apoyo rechazado', 'Avisa al solicitante que el mesero invitado no puede incorporarse.', ['ver mesas'], 'bx-user-x'),
                    'table.support_assigned' => self::event('Asignación como apoyo', 'Avisa a un mesero cuando el responsable lo agrega directamente al equipo de una mesa.', ['ver mesas'], 'bx-group'),
                ],
            ],
            'counter' => [
                'label' => 'Ventanilla y recoger',
                'icon' => 'bx-store-alt',
                'description' => 'Pedidos capturados en el punto de venta para entrega en mostrador.',
                'events' => [
                    'counter.order_created' => self::event('Nuevo pedido de ventanilla', 'Avisa cuando se registra una venta nueva para entrega inmediata en ventanilla.', ['ver pedidos en punto de venta', 'ver ordenes'], 'bx-receipt'),
                    'counter.order_ready' => self::event('Pedido de ventanilla listo', 'Avisa cuando cocina termina un pedido que debe entregarse en ventanilla.', ['ver pedidos en punto de venta', 'ver ordenes'], 'bx-check-circle'),
                    'pickup.order_created' => self::event('Nuevo pedido para recoger', 'Avisa cuando se registra un pedido que el cliente recogerá posteriormente.', ['ver pedidos en punto de venta', 'ver ordenes'], 'bx-package'),
                    'pickup.order_ready' => self::event('Pedido para recoger listo', 'Avisa cuando el pedido ya puede entregarse al cliente que pasa a recoger.', ['ver pedidos en punto de venta', 'ver ordenes'], 'bx-check-double'),
                ],
            ],
            'kiosk' => [
                'label' => 'Kiosco',
                'icon' => 'bx-devices',
                'description' => 'Pedidos capturados desde terminales de autoservicio.',
                'events' => [
                    'kiosk.order_created' => self::event('Nuevo pedido de kiosco', 'Avisa cuando un cliente confirma un pedido desde un kiosco.', ['gestionar kioscos', 'ver pedidos en punto de venta', 'ver ordenes'], 'bx-devices'),
                    'kiosk.order_ready' => self::event('Pedido de kiosco listo', 'Avisa cuando cocina termina un pedido originado en un kiosco.', ['gestionar kioscos', 'ver pedidos en punto de venta', 'ver ordenes'], 'bx-check-circle'),
                ],
            ],
            'orders' => [
                'label' => 'Seguimiento general',
                'icon' => 'bx-receipt',
                'description' => 'Cancelaciones, cobros y excepciones aplicables a cualquier canal.',
                'events' => [
                    'order.cancelled' => self::event('Pedido cancelado', 'Avisa cuando una orden cambia al estado cancelada.', ['ver ordenes', 'ver mesas', 'ver pedidos en punto de venta'], 'bx-x-circle'),
                    'order.paid' => self::event('Pedido cobrado', 'Confirma cuando un pedido queda pagado y finaliza su ciclo de cobro.', ['ver ordenes', 'ver caja', 'ver pedidos en punto de venta'], 'bx-credit-card'),
                ],
            ],
            'authorizations' => [
                'label' => 'Autorizaciones',
                'icon' => 'bx-shield-quarter',
                'description' => 'Solicitudes que requieren revisión antes de modificar una orden.',
                'events' => [
                    'order.cancellation_requested' => self::event('Solicitud de cancelación', 'Avisa cuando un usuario solicita cancelar total o parcialmente una orden.', ['revisar solicitudes de ordenes'], 'bx-error-circle'),
                    'order.modification_requested' => self::event('Solicitud de modificación', 'Avisa cuando un usuario solicita agregar, retirar o cambiar productos de una orden.', ['revisar solicitudes de ordenes'], 'bx-edit-alt'),
                    'order.payment_change_requested' => self::event('Solicitud de cambio de pago', 'Avisa cuando se solicita corregir el método de pago de un delivery sin duplicar el cobro.', ['revisar solicitudes de ordenes'], 'bx-credit-card'),
                    'order.address_change_requested' => self::event('Solicitud de cambio de dirección', 'Avisa cuando un cliente solicita modificar el destino de un delivery activo.', ['revisar solicitudes de ordenes'], 'bx-map'),
                    'order.change_approved' => self::event('Cambio de orden aprobado', 'Confirma al solicitante o repartidor que el cambio autorizado ya fue aplicado.', ['solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes', 'solicitar cambio de metodo de pago', 'solicitar cambio de direccion', 'ver delivery'], 'bx-check-shield'),
                    'order.change_rejected' => self::event('Cambio de orden rechazado', 'Informa al solicitante que la orden permanece sin cambios y muestra la resolución.', ['solicitar cancelacion de ordenes', 'solicitar modificacion de ordenes', 'solicitar cambio de metodo de pago', 'solicitar cambio de direccion'], 'bx-x-circle'),
                ],
            ],
            'delivery' => [
                'label' => 'Delivery',
                'icon' => 'bx-cycling',
                'description' => 'Disponibilidad, asignación y seguimiento de entregas administradas.',
                'events' => [
                    'delivery.order_created' => self::event('Nuevo pedido de delivery', 'Avisa cuando se registra un nuevo pedido con entrega a domicilio.', ['ver delivery', 'ver pedidos en punto de venta', 'ver ordenes'], 'bx-plus-circle'),
                    'delivery.order_ready' => self::event('Pedido de delivery listo', 'Avisa cuando un delivery directo queda preparado sin usar la asignación administrada.', ['ver pedidos en punto de venta', 'ver ordenes', 'ver caja'], 'bx-check-circle'),
                    'delivery.available' => self::event('Nuevo pedido en espera para tomar (delivery)', 'Avisa cuando un delivery administrado está listo y disponible para que un repartidor lo tome.', ['ver delivery'], 'bx-package'),
                    'delivery.assigned' => self::event('Delivery asignado', 'Avisa cuando una entrega se asigna a un repartidor.', ['ver delivery'], 'bx-user-check'),
                    'delivery.reassigned' => self::event('Delivery reasignado', 'Avisa cuando un pedido cambia de repartidor e incluye el motivo operativo.', ['ver delivery'], 'bx-transfer-alt'),
                    'delivery.picked_up' => self::event('Delivery recogido', 'Avisa cuando el repartidor recoge el pedido y comienza el trayecto.', ['ver delivery', 'ver ordenes', 'ver caja'], 'bx-run'),
                    'delivery.completed' => self::event('Delivery completado', 'Confirma que la entrega terminó y el pedido quedó liquidado.', ['ver delivery', 'ver ordenes', 'ver caja'], 'bx-home-heart'),
                ],
            ],
        ];
    }

    public static function all(): array
    {
        return collect(self::definitions())
            ->flatMap(fn (array $group): array => $group['events'])
            ->all();
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $eventKey): ?array
    {
        return self::all()[$eventKey] ?? null;
    }

    private static function event(string $label, string $description, array $permissions, string $icon): array
    {
        return compact('label', 'description', 'permissions', 'icon');
    }
}

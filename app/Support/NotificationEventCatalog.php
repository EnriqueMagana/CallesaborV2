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
            'orders' => [
                'label' => 'Pedidos',
                'icon' => 'bx-receipt',
                'description' => 'Altas, preparación, cobro y excepciones de pedidos.',
                'events' => [
                    'order.created' => self::event('Pedido nuevo', 'Avisa cuando se registra un pedido nuevo de ventanilla, recoger, kiosco, mesa o delivery.', ['ver ordenes', 'ver mesas', 'ver pedidos en punto de venta'], 'bx-plus-circle'),
                    'order.ready' => self::event('Pedido listo', 'Avisa cuando cocina termina un pedido y queda listo para entregar o cobrar.', ['ver ordenes', 'ver mesas', 'ver pedidos en punto de venta'], 'bx-check-circle'),
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
                ],
            ],
            'delivery' => [
                'label' => 'Delivery',
                'icon' => 'bx-cycling',
                'description' => 'Disponibilidad, asignación y seguimiento de entregas administradas.',
                'events' => [
                    'delivery.available' => self::event('Delivery disponible', 'Avisa cuando un pedido está listo para que un repartidor lo tome.', ['ver delivery'], 'bx-package'),
                    'delivery.assigned' => self::event('Delivery asignado', 'Avisa cuando una entrega se asigna a un repartidor.', ['ver delivery'], 'bx-user-check'),
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

<?php

namespace App\Console\Commands;

use App\Models\CashRegister;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Lista las órdenes cuyo total quedó por encima del dinero realmente recibido
 * sin que nadie tenga asignado cobrar la diferencia (lo que trae el repartidor
 * contra entrega no cuenta como descuadre).
 * Es el rastro que dejaron las modificaciones aprobadas antes de que existiera
 * el saldo pendiente: el ticket imprimía un total mayor al cobrado.
 *
 * Sólo lee: no corrige ni cobra nada.
 */
class AuditOrderBalances extends Command
{
    protected $signature = 'ordenes:auditar-saldos
        {--dias=90 : Antigüedad máxima de las órdenes a revisar}
        {--min=0.01 : Saldo mínimo para reportar una orden}
        {--solo-cajas-abiertas : Limita el reporte a cajas todavía abiertas}';

    protected $description = 'Reporta órdenes con saldo pendiente: total cobrado menor al total de la orden';

    public function handle(): int
    {
        $minimum = (float) $this->option('min');
        $since = now()->subDays((int) $this->option('dias'));

        $orders = Order::query()
            ->with(['cashRegister', 'payments', 'refunds'])
            ->where('status', '!=', 'cancelada')
            ->where('created_at', '>=', $since)
            ->whereHas('payments')
            ->when($this->option('solo-cajas-abiertas'), fn ($query) => $query->whereIn(
                'cash_register_id',
                CashRegister::query()->where('is_open', true)->select('id')
            ))
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Order $order) => $order->uncovered_amount >= $minimum);

        if ($orders->isEmpty()) {
            $this->components->info('Sin descuadres: todas las órdenes cobradas cubren su total vigente.');

            return self::SUCCESS;
        }

        $this->components->warn($orders->count().' orden(es) con saldo pendiente.');

        $this->table(
            ['Folio', 'Fecha', 'Tipo', 'Estado', 'Caja', 'Total', 'Cobrado', 'Saldo', 'Modificada'],
            $orders->map(fn (Order $order) => [
                $order->display_folio,
                $order->created_at?->format('Y-m-d H:i'),
                $order->type,
                $order->status,
                $order->cashRegister?->is_open ? 'abierta' : 'cerrada',
                number_format((float) $order->total, 2),
                number_format($order->net_paid_amount, 2),
                number_format($order->uncovered_amount, 2),
                $order->changeRequests()->where('status', 'approved')->exists() ? 'sí' : 'no',
            ])->all()
        );

        $this->newLine();
        $this->components->info('Saldo total sin cobrar: $'.number_format($orders->sum(fn (Order $order) => $order->uncovered_amount), 2));
        $openRegisters = $orders->filter(fn (Order $order) => (bool) $order->cashRegister?->is_open);
        if ($openRegisters->isNotEmpty()) {
            $this->components->info(
                $openRegisters->count().' de ellas están en cajas abiertas y pueden cobrarse desde el POS '
                .'(el modal de cobro ahora pide sólo el saldo).'
            );
        }
        if ($openRegisters->count() < $orders->count()) {
            $this->components->warn(
                ($orders->count() - $openRegisters->count()).' pertenecen a cajas ya cerradas: '
                .'cobrarlas alteraría cortes históricos, resuélvelas contablemente.'
            );
        }

        return self::SUCCESS;
    }
}

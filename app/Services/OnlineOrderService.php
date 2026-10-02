<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\OnlineOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnlineOrderService
{
    public function submit(array $data): OnlineOrder
    {
        app(OnlineSalesPolicy::class)->assertEnabled();
        $priced = app(OnlineOrderCartService::class)->price($data['items'], $data['fulfillment']);
        $cashTendered = $data['payment_method'] === 'efectivo' && isset($data['cash_tendered'])
            ? round((float) $data['cash_tendered'], 2)
            : null;

        if ($data['payment_method'] === 'efectivo' && ($cashTendered === null || $cashTendered + 0.009 < $priced['total'])) {
            throw ValidationException::withMessages(['cash_tendered' => 'Indica un monto suficiente para calcular el cambio.']);
        }

        return DB::transaction(function () use ($data, $priced, $cashTendered): OnlineOrder {
            $onlineOrder = OnlineOrder::create([
                'folio' => 'TMP-'.Str::random(20),
                'public_token' => Str::random(64),
                'status' => 'awaiting_whatsapp',
                'fulfillment' => $data['fulfillment'],
                'customer_name' => trim($data['customer_name']),
                'customer_phone' => preg_replace('/\D+/', '', $data['customer_phone']),
                'customer_address' => $data['fulfillment'] === 'delivery' ? trim($data['customer_address']) : null,
                'customer_neighborhood' => $data['fulfillment'] === 'delivery' ? trim($data['customer_neighborhood']) : null,
                'customer_references' => $data['fulfillment'] === 'delivery' ? trim((string) ($data['customer_references'] ?? '')) ?: null : null,
                'payment_method' => $data['payment_method'],
                'cash_tendered' => $cashTendered,
                'subtotal' => $priced['subtotal'],
                'total' => $priced['total'],
                'cart_snapshot' => $priced['lines'],
                'customer_notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ]);

            $onlineOrder->update([
                'folio' => 'MD-'.str_pad((string) $onlineOrder->id, 4, '0', STR_PAD_LEFT),
            ]);

            return $onlineOrder;
        });
    }

    public function confirm(OnlineOrder $onlineOrder, User $user): Order
    {
        return DB::transaction(function () use ($onlineOrder, $user): Order {
            $onlineOrder = OnlineOrder::query()->lockForUpdate()->findOrFail($onlineOrder->id);
            if ($onlineOrder->order_id) {
                return Order::findOrFail($onlineOrder->order_id);
            }
            if ($onlineOrder->status === 'rejected') {
                throw ValidationException::withMessages(['onlineOrder' => 'Este pedido fue marcado como no concretado.']);
            }

            $cash = CashRegister::query()->where('is_open', true)->latest('opened_at')->lockForUpdate()->first();
            if (! $cash) {
                throw ValidationException::withMessages(['onlineOrder' => 'Abre una caja antes de confirmar el pedido.']);
            }

            $customer = Customer::query()->where('phone', $onlineOrder->customer_phone)->lockForUpdate()->first();
            $customerData = [
                'name' => $onlineOrder->customer_name,
                'phone' => $onlineOrder->customer_phone,
                'address' => $onlineOrder->customer_address,
                'neighborhood' => $onlineOrder->customer_neighborhood,
                'references' => $onlineOrder->customer_references,
            ];
            $customer ? $customer->update(collect($customerData)->filter()->all()) : $customer = Customer::create($customerData);

            $delivery = $onlineOrder->fulfillment === 'delivery';
            $managed = ! $delivery || app(DeliveryModulePolicy::class)->enabledForUpdate();
            $order = Order::create([
                'cash_register_id' => $cash->id,
                'customer_id' => $customer->id,
                'public_token' => $onlineOrder->public_token,
                'customer_name' => $onlineOrder->customer_name,
                'customer_phone' => $onlineOrder->customer_phone,
                'customer_address' => $onlineOrder->customer_address,
                'customer_neighborhood' => $onlineOrder->customer_neighborhood,
                'customer_references' => $onlineOrder->customer_references,
                'served_by' => $user->id,
                'type' => $delivery ? 'delivery' : 'ventanilla',
                'source' => 'online',
                'fulfillment' => $onlineOrder->fulfillment,
                'preferred_payment_method' => $onlineOrder->payment_method,
                'cash_tendered' => $onlineOrder->cash_tendered,
                'delivery_method' => $delivery ? 'contra_entrega' : null,
                'delivery_flow_mode' => $delivery && ! $managed ? 'manual' : 'managed',
                'status' => 'pendiente',
                'subtotal' => $onlineOrder->subtotal,
                'total' => $onlineOrder->total,
                'notes' => collect([$onlineOrder->display_folio, $onlineOrder->customer_notes])->filter()->implode(' · '),
            ]);

            foreach ($onlineOrder->cart_snapshot as $line) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'] ?? null,
                    'promotion_id' => $line['promotion_id'] ?? null,
                    'product_name' => $line['product_name'],
                    'product_price' => $line['product_price'],
                    'quantity' => $line['quantity'],
                    'subtotal' => $line['subtotal'],
                    'promotion_discount' => $line['promotion_discount'] ?? 0,
                    'notes' => $line['notes'] ?: null,
                    'promotion_selections' => $line['promotion_selections'] ?? null,
                    'promotion_rule_snapshot' => $line['promotion_rule_snapshot'] ?? null,
                ]);
                foreach ($line['addons'] ?? [] as $addon) {
                    OrderItemAddon::create(['order_item_id' => $item->id, 'addon_id' => $addon['id'], 'addon_name' => $addon['name'], 'extra_price' => $addon['extra_price'], 'quantity' => $addon['quantity']]);
                }
                foreach ($line['ingredients'] ?? [] as $ingredient) {
                    OrderItemIngredient::create(['order_item_id' => $item->id, 'ingredient_id' => $ingredient['id'], 'ingredient_name' => $ingredient['name'], 'extra_price' => $ingredient['extra_price'], 'quantity' => $ingredient['quantity']]);
                }
            }

            if ($delivery && ! $managed) {
                app(ManualDeliveryAccountingService::class)->account($order);
            }

            $onlineOrder->update(['order_id' => $order->id, 'status' => 'confirmed', 'confirmed_by' => $user->id, 'confirmed_at' => now()]);

            return $order->load(['items.addons', 'items.ingredients']);
        });
    }

    public function whatsappMessage(OnlineOrder $order): string
    {
        $lines = collect($order->cart_snapshot)->flatMap(function (array $line): array {
            $details = collect($line['addons'] ?? [])->map(fn ($a) => '+ '.$a['name'].' x'.$a['quantity'])
                ->merge(collect($line['ingredients'] ?? [])->map(fn ($i) => '• '.$i['name'].' x'.$i['quantity']))
                ->all();
            if (! empty($line['promotion_selections'])) {
                $details[] = 'Combo: '.collect($line['promotion_selections'])
                    ->flatMap(fn (array $group) => collect($group['items'] ?? [])->map(fn (array $item) => $item['product_name'].' x'.$item['quantity']))
                    ->implode(', ');
            }

            return array_merge([$line['quantity'].' x '.$line['product_name'].' — $'.number_format($line['subtotal'], 2)], $details, $line['notes'] ? ['Nota: '.$line['notes']] : []);
        });
        $payment = match ($order->payment_method) {
            'efectivo' => 'Efectivo'.($order->cash_tendered ? ' (paga con $'.number_format((float) $order->cash_tendered, 2).')' : ''), 'tarjeta' => 'Tarjeta', default => 'Transferencia'
        };
        $address = $order->fulfillment === 'delivery'
            ? "\nDirección: {$order->customer_address}, {$order->customer_neighborhood}".($order->customer_references ? "\nReferencias: {$order->customer_references}" : '')
            : '';

        return "Hola, quiero confirmar la {$order->display_folio}.\nOrigen: Menú Digital\n\nCLIENTE\n{$order->customer_name}\nTel: {$order->customer_phone}\nServicio: {$order->fulfillment_label}{$address}\n\nPEDIDO\n".$lines->implode("\n")."\n\nTOTAL: $".number_format((float) $order->total, 2)."\nPago: {$payment}";
    }
}

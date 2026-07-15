<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\AddonGroup;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddon;
use App\Models\OrderItemIngredient;
use App\Models\OrderPayment;
use App\Models\PrintArea;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestOrderSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::first();

        // ─── Print Area ────────────────────────────────────────────────────────
        $area = PrintArea::firstOrCreate(
            ['name' => 'Cocina'],
            ['description' => 'Área principal de cocina', 'color' => '#ff6b35', 'is_active' => true]
        );

        // ─── Category ─────────────────────────────────────────────────────────
        $category = Category::firstOrCreate(
            ['name' => 'Pastas'],
            [
                'description'   => 'Pastas artesanales',
                'icon'          => 'bx-dish',
                'color'         => '#696cff',
                'print_area_id' => $area->id,
                'is_active'     => true,
            ]
        );

        // ─── Addon Group ───────────────────────────────────────────────────────
        $addonGroup = AddonGroup::firstOrCreate(
            ['name' => 'Tipo de Carne'],
            [
                'description'    => 'Elige tu proteína',
                'is_required'    => true,
                'min_selections' => 1,
                'max_selections' => 1,
                'is_active'      => true,
            ]
        );

        $addonPollo = Addon::firstOrCreate(
            ['name' => 'Pollo plancha', 'addon_group_id' => $addonGroup->id],
            ['extra_price' => 0, 'is_active' => true]
        );

        $addonCrispy = Addon::firstOrCreate(
            ['name' => 'Pollo crispy', 'addon_group_id' => $addonGroup->id],
            ['extra_price' => 0, 'is_active' => true]
        );

        $addonRes = Addon::firstOrCreate(
            ['name' => 'Res a la plancha', 'addon_group_id' => $addonGroup->id],
            ['extra_price' => 25.00, 'is_active' => true]
        );

        // ─── Sauce addon group ─────────────────────────────────────────────────
        $sauceGroup = AddonGroup::firstOrCreate(
            ['name' => 'Salsa'],
            [
                'description'    => 'Elige tu salsa',
                'is_required'    => false,
                'min_selections' => 0,
                'max_selections' => 2,
                'is_active'      => true,
            ]
        );

        $sauceAlfredo = Addon::firstOrCreate(
            ['name' => 'Alfredo', 'addon_group_id' => $sauceGroup->id],
            ['extra_price' => 0, 'is_active' => true]
        );

        $sauceRosa = Addon::firstOrCreate(
            ['name' => 'Salsa Rosa', 'addon_group_id' => $sauceGroup->id],
            ['extra_price' => 10.00, 'is_active' => true]
        );

        // ─── Ingredients ──────────────────────────────────────────────────────
        $brocoli = Ingredient::firstOrCreate(
            ['name' => 'Brócoli'],
            ['extra_price' => 0, 'is_active' => true]
        );

        $zanahoria = Ingredient::firstOrCreate(
            ['name' => 'Zanahoria'],
            ['extra_price' => 0, 'is_active' => true]
        );

        $calabaza = Ingredient::firstOrCreate(
            ['name' => 'Calabaza'],
            ['extra_price' => 0, 'is_active' => true]
        );

        $champi = Ingredient::firstOrCreate(
            ['name' => 'Champiñones'],
            ['extra_price' => 15.00, 'is_active' => true]
        );

        // ─── Product ──────────────────────────────────────────────────────────
        $product = Product::firstOrCreate(
            ['name' => 'Pasta grande'],
            [
                'description'    => 'Pasta artesanal a elegir con proteína y salsa',
                'category_id'    => $category->id,
                'price'          => 165.00,
                'is_customizable'=> true,
                'min_ingredients'=> 0,
                'max_ingredients'=> 6,
                'is_active'      => true,
            ]
        );

        // Actualizar categoría y límites si el producto ya existía
        $product->update([
            'category_id'    => $category->id,
            'is_customizable'=> true,
            'min_ingredients'=> 0,
            'max_ingredients'=> 6,
        ]);

        // Sync addon groups
        $product->addonGroups()->syncWithoutDetaching([
            $addonGroup->id => ['sort_order' => 0],
            $sauceGroup->id => ['sort_order' => 1],
        ]);

        // Sync ingredients
        $product->ingredients()->syncWithoutDetaching([
            $brocoli->id   => ['sort_order' => 0],
            $zanahoria->id => ['sort_order' => 1],
            $calabaza->id  => ['sort_order' => 2],
            $champi->id    => ['sort_order' => 3],
        ]);

        // ─── Customer ─────────────────────────────────────────────────────────
        $customer = Customer::firstOrCreate(
            ['phone' => '5512345678'],
            [
                'name'       => 'María López',
                'address'    => 'Calle Sabor 123, Col. Centro',
                'references' => 'Entre la panadería y la farmacia',
            ]
        );

        // ─── Cash Register ────────────────────────────────────────────────────
        $cashRegister = CashRegister::firstOrCreate(
            ['name' => 'Caja 1'],
            [
                'opened_by'      => $seller->id,
                'initial_amount' => 500.00,
                'opened_at'      => now()->setTime(8, 0),
                'is_open'        => true,
            ]
        );

        // ─── Order ────────────────────────────────────────────────────────────
        // Precio del ítem: producto + addon res (+25) + salsa rosa (+10) + champiñones x2 (+30) = 230
        $addonResPrice   = 25.00;
        $sauceRosaPrice  = 10.00;
        $champiUnitPrice = 15.00;
        $champiQty       = 2;
        $itemQty         = 1;

        $itemSubtotal = ($product->price + $addonResPrice + $sauceRosaPrice + ($champiUnitPrice * $champiQty)) * $itemQty;
        // 165 + 25 + 10 + 30 = 230

        $order = Order::create([
            'cash_register_id' => $cashRegister->id,
            'customer_id'      => $customer->id,
            'customer_name'    => $customer->name,
            'customer_phone'   => $customer->phone,
            'served_by'        => $seller->id,
            'type'             => 'ventanilla',
            'status'           => 'pagada',
            'subtotal'         => $itemSubtotal,
            'total'            => $itemSubtotal,
            'notes'            => 'Sin picante por favor.',
            'paid_at'          => now(),
        ]);

        // ─── Order Item ───────────────────────────────────────────────────────
        $item = OrderItem::create([
            'order_id'      => $order->id,
            'product_id'    => $product->id,
            'product_name'  => $product->name,
            'product_price' => $product->price,
            'quantity'      => $itemQty,
            'subtotal'      => $itemSubtotal,
            'notes'         => null,
        ]);

        // Addons del ítem
        OrderItemAddon::create([
            'order_item_id' => $item->id,
            'addon_id'      => $addonRes->id,
            'addon_name'    => $addonRes->name,
            'extra_price'   => $addonResPrice,
            'quantity'      => 1,
        ]);

        OrderItemAddon::create([
            'order_item_id' => $item->id,
            'addon_id'      => $sauceRosa->id,
            'addon_name'    => $sauceRosa->name,
            'extra_price'   => $sauceRosaPrice,
            'quantity'      => 1,
        ]);

        // Ingredientes del ítem (brócoli x2, zanahoria x2, champiñones x2 = 6 total)
        OrderItemIngredient::create([
            'order_item_id'   => $item->id,
            'ingredient_id'   => $brocoli->id,
            'ingredient_name' => $brocoli->name,
            'extra_price'     => 0,
            'quantity'        => 2,
        ]);

        OrderItemIngredient::create([
            'order_item_id'   => $item->id,
            'ingredient_id'   => $zanahoria->id,
            'ingredient_name' => $zanahoria->name,
            'extra_price'     => 0,
            'quantity'        => 2,
        ]);

        OrderItemIngredient::create([
            'order_item_id'   => $item->id,
            'ingredient_id'   => $champi->id,
            'ingredient_name' => $champi->name,
            'extra_price'     => $champiUnitPrice,
            'quantity'        => $champiQty,
        ]);

        // ─── Payments ─────────────────────────────────────────────────────────
        // Pagó mixto: $150 efectivo + $80 tarjeta = $230 exacto
        OrderPayment::create([
            'order_id'        => $order->id,
            'method'          => 'efectivo',
            'amount'          => 150.00,
            'received_amount' => 200.00,
            'change_amount'   => 50.00,
        ]);

        OrderPayment::create([
            'order_id' => $order->id,
            'method'   => 'tarjeta',
            'amount'   => 80.00,
        ]);

        $this->command->info("✅ Orden #{$order->id} creada — Total: \${$order->total} — Cliente: {$customer->name}");
        $this->command->info("   Producto: {$product->name} | Tipo: ventanilla | Estado: pagada");
        $this->command->info("   Addons: Res a la plancha (+\$25), Salsa Rosa (+\$10)");
        $this->command->info("   Ingredientes: Brócoli x2, Zanahoria x2, Champiñones x2 (+\$30)");
        $this->command->info("   Pagos: Efectivo \$150 (recibió \$200, cambio \$50) + Tarjeta \$80");
    }
}

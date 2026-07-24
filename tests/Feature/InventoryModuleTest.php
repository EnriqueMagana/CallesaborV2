<?php

namespace Tests\Feature;

use App\Livewire\Inventory\InventoryManager;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryPurchase;
use App\Models\TicketTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_guest_cannot_access_inventory(): void
    {
        $this->get(route('app.inventario'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_open_the_modern_inventory_module(): void
    {
        $user = $this->userWithPermissions(['ver inventario']);

        $this->actingAs($user)
            ->get(route('app.inventario'))
            ->assertOk()
            ->assertSee('Inventario de insumos')
            ->assertSee('inventory-initial-skeleton', false)
            ->assertSee('Compras y recepciones');
    }

    public function test_manual_adjustments_are_permission_protected_and_never_allow_negative_stock(): void
    {
        $viewer = $this->userWithPermissions(['ver inventario']);
        $item = InventoryItem::create([
            'name' => 'Aceite vegetal',
            'unit' => 'liter',
            'current_stock' => 5,
            'minimum_stock' => 2,
        ]);

        Livewire::actingAs($viewer)
            ->test(InventoryManager::class)
            ->call('openAdjustmentModal', $item->id, 'out')
            ->assertForbidden();

        $operator = $this->userWithPermissions(['ver inventario', 'ajustar inventario']);
        $component = Livewire::actingAs($operator)
            ->test(InventoryManager::class)
            ->call('openAdjustmentModal', $item->id, 'in')
            ->set('adjustQuantity', '2.500')
            ->set('adjustReason', 'Entrada por conteo físico')
            ->call('saveAdjustment')
            ->assertHasNoErrors();

        $this->assertEquals(7.5, (float) $item->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'user_id' => $operator->id,
            'type' => 'manual_in',
            'stock_after' => 7.5,
        ]);

        $component
            ->call('openAdjustmentModal', $item->id, 'out')
            ->set('adjustQuantity', '8')
            ->set('adjustReason', 'Salida mayor a existencia')
            ->call('saveAdjustment')
            ->assertHasErrors(['adjustQuantity']);

        $this->assertEquals(7.5, (float) $item->fresh()->current_stock);
    }

    public function test_manager_can_create_an_item_with_an_audited_opening_balance(): void
    {
        $user = $this->userWithPermissions([
            'ver inventario',
            'gestionar insumos',
            'ajustar inventario',
        ]);

        Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openItemModal')
            ->set('itemName', 'Refresco de cola')
            ->set('itemSku', ' ref-001 ')
            ->set('itemCategory', 'Bebidas')
            ->set('itemUnit', 'box')
            ->set('minimumStock', '3')
            ->set('estimatedUnitCost', '280.50')
            ->set('openingStock', '12.5')
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = InventoryItem::where('sku', 'REF-001')->firstOrFail();
        $this->assertEquals(12.5, (float) $item->current_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_item_id' => $item->id,
            'user_id' => $user->id,
            'type' => 'opening_balance',
            'quantity' => 12.5,
            'stock_before' => 0,
            'stock_after' => 12.5,
        ]);
    }

    public function test_purchase_ticket_and_reception_update_inventory_once(): void
    {
        $user = $this->userWithPermissions([
            'ver inventario',
            'generar compras inventario',
            'editar compras inventario',
            'eliminar compras inventario',
            'recepcionar compras inventario',
        ]);
        $item = InventoryItem::create([
            'name' => 'Harina de trigo',
            'sku' => 'HAR-001',
            'unit' => 'kilogram',
            'current_stock' => 3,
            'minimum_stock' => 5,
            'estimated_unit_cost' => 28.50,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openPurchaseModal')
            ->set('purchaseLines', [[
                'inventory_item_id' => $item->id,
                'quantity' => '10',
                'notes' => 'Costal cerrado',
            ], [
                'inventory_item_id' => '',
                'quantity' => '',
                'notes' => '',
            ]])
            ->set('purchaseNotes', 'Comprar con proveedor habitual')
            ->call('createPurchase')
            ->assertHasNoErrors()
            ->assertSet('activeTab', 'purchases');

        $purchase = InventoryPurchase::with('items')->firstOrFail();
        $line = $purchase->items->first();

        $this->assertMatchesRegularExpression('/^CMP-\d{4}-\d{6}$/', $purchase->folio);
        $this->assertCount(1, $purchase->items);
        $this->actingAs($user)
            ->get(route('print.inventory-purchase', $purchase))
            ->assertOk()
            ->assertSee($purchase->folio)
            ->assertSee('Harina de trigo')
            ->assertSee('10 kg');

        $component = Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openReceptionModal', $purchase->id)
            ->assertSet('receptionFolio', $purchase->folio)
            ->set("receptionQuantities.{$line->id}", '8')
            ->call('confirmReception')
            ->assertHasErrors(["receptionNotes.{$line->id}"]);

        $component
            ->set("receptionNotes.{$line->id}", 'El proveedor solo tenía ocho kilos')
            ->call('confirmReception')
            ->assertHasNoErrors();

        $this->assertEquals(11.0, (float) $item->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_purchases', [
            'id' => $purchase->id,
            'status' => 'received',
            'received_by' => $user->id,
        ]);
        $this->assertDatabaseHas('inventory_purchase_items', [
            'id' => $line->id,
            'received_quantity' => 8,
            'reception_note' => 'El proveedor solo tenía ocho kilos',
        ]);
        $this->assertSame(1, InventoryMovement::where('inventory_purchase_id', $purchase->id)->count());

        $component->call('confirmReception')->assertHasErrors(['receptionFolio']);
        $this->assertEquals(11.0, (float) $item->fresh()->current_stock);
        $this->assertSame(1, InventoryMovement::where('inventory_purchase_id', $purchase->id)->count());
    }

    public function test_purchase_validation_messages_are_clear_and_in_spanish(): void
    {
        $user = $this->userWithPermissions([
            'ver inventario',
            'generar compras inventario',
            'editar compras inventario',
            'eliminar compras inventario',
        ]);
        $item = InventoryItem::create([
            'name' => 'Servilletas',
            'unit' => 'package',
            'current_stock' => 2,
            'minimum_stock' => 4,
        ]);

        Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openPurchaseModal')
            ->set('purchaseLines', [[
                'inventory_item_id' => $item->id,
                'quantity' => '',
                'notes' => '',
            ]])
            ->call('createPurchase')
            ->assertHasErrors(['purchaseLines.0.quantity'])
            ->assertSee('Indica la cantidad que necesitas comprar.');
    }

    public function test_purchase_opens_a_preview_and_pending_purchase_can_be_edited_and_deleted(): void
    {
        $user = $this->userWithPermissions([
            'ver inventario',
            'generar compras inventario',
            'editar compras inventario',
            'eliminar compras inventario',
        ]);
        $flour = InventoryItem::create([
            'name' => 'Harina',
            'unit' => 'kilogram',
            'current_stock' => 2,
            'minimum_stock' => 5,
        ]);
        $oil = InventoryItem::create([
            'name' => 'Aceite',
            'unit' => 'liter',
            'current_stock' => 1,
            'minimum_stock' => 3,
        ]);

        $component = Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openPurchaseModal')
            ->set('purchaseLines', [[
                'inventory_item_id' => $flour->id,
                'quantity' => '4',
                'notes' => '',
            ]])
            ->call('createPurchase')
            ->assertHasNoErrors()
            ->assertSet('showPurchaseDetailModal', true);

        $purchase = InventoryPurchase::firstOrFail();
        $component
            ->call('editPurchase', $purchase->id)
            ->assertSet('editingPurchaseId', $purchase->id)
            ->set('purchaseLines', [[
                'inventory_item_id' => $oil->id,
                'quantity' => '6',
                'notes' => 'Botella sellada',
            ]])
            ->set('purchaseNotes', 'Compra actualizada')
            ->call('createPurchase')
            ->assertHasNoErrors()
            ->assertSet('showPurchaseDetailModal', true);

        $this->assertDatabaseCount('inventory_purchases', 1);
        $this->assertDatabaseMissing('inventory_purchase_items', [
            'inventory_purchase_id' => $purchase->id,
            'inventory_item_id' => $flour->id,
        ]);
        $this->assertDatabaseHas('inventory_purchase_items', [
            'inventory_purchase_id' => $purchase->id,
            'inventory_item_id' => $oil->id,
            'requested_quantity' => 6,
        ]);

        $component
            ->call('askDeletePurchase')
            ->assertSet('showDeletePurchaseConfirm', true)
            ->call('deletePurchase')
            ->assertHasNoErrors()
            ->assertSet('showPurchaseDetailModal', false);

        $this->assertDatabaseCount('inventory_purchases', 0);
    }

    public function test_received_purchase_remains_read_only_and_cannot_be_deleted(): void
    {
        $user = $this->userWithPermissions([
            'ver inventario',
            'generar compras inventario',
            'editar compras inventario',
            'eliminar compras inventario',
        ]);
        $purchase = InventoryPurchase::create([
            'folio' => 'CMP-2607-000777',
            'status' => 'received',
            'requested_by' => $user->id,
            'received_by' => $user->id,
            'issued_at' => now()->subHour(),
            'received_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openPurchaseDetail', $purchase->id)
            ->call('editPurchase', $purchase->id)
            ->assertStatus(422);

        Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('openPurchaseDetail', $purchase->id)
            ->call('askDeletePurchase')
            ->assertStatus(422);

        $this->assertDatabaseHas('inventory_purchases', ['id' => $purchase->id]);
    }

    public function test_purchase_creation_editing_and_deletion_have_independent_permissions(): void
    {
        $creator = $this->userWithPermissions([
            'ver inventario',
            'generar compras inventario',
        ]);
        $purchase = InventoryPurchase::create([
            'folio' => 'CMP-2607-000778',
            'status' => 'pending',
            'requested_by' => $creator->id,
            'issued_at' => now(),
        ]);

        Livewire::actingAs($creator)
            ->test(InventoryManager::class)
            ->call('editPurchase', $purchase->id)
            ->assertForbidden();

        Livewire::actingAs($creator)
            ->test(InventoryManager::class)
            ->call('openPurchaseDetail', $purchase->id)
            ->call('askDeletePurchase')
            ->assertForbidden();

        $editor = $this->userWithPermissions([
            'ver inventario',
            'editar compras inventario',
        ]);
        Livewire::actingAs($editor)
            ->test(InventoryManager::class)
            ->call('editPurchase', $purchase->id)
            ->assertSet('editingPurchaseId', $purchase->id);

        $deleter = $this->userWithPermissions([
            'ver inventario',
            'eliminar compras inventario',
        ]);
        Livewire::actingAs($deleter)
            ->test(InventoryManager::class)
            ->call('openPurchaseDetail', $purchase->id)
            ->call('askDeletePurchase')
            ->call('deletePurchase')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('inventory_purchases', ['id' => $purchase->id]);
    }

    public function test_inventory_purchase_ticket_uses_ticket_maker_and_only_autoprints_when_requested(): void
    {
        $user = $this->userWithPermissions([
            'ver inventario',
            'generar compras inventario',
        ]);
        $item = InventoryItem::create([
            'name' => 'Servilletas premium',
            'unit' => 'package',
            'current_stock' => 0,
            'minimum_stock' => 2,
        ]);
        $purchase = InventoryPurchase::create([
            'folio' => 'CMP-2607-000888',
            'status' => 'pending',
            'requested_by' => $user->id,
            'issued_at' => now(),
            'notes' => 'Revisar presentación',
        ]);
        $purchase->items()->create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'unit' => $item->unit,
            'requested_quantity' => 3,
        ]);

        $template = TicketTemplate::current('inventory_purchase');
        $template->update([
            'paper_width_mm' => 58,
            'font_size' => 14,
            'footer_text' => 'Pie definido desde Ticket Maker',
        ]);

        $preview = $this->actingAs($user)->get(route('print.inventory-purchase', $purchase));
        $preview->assertOk()
            ->assertSee('ticket-paper-58', false)
            ->assertSee('ticket-font-14', false)
            ->assertSee('Servilletas premium')
            ->assertSee('Pie definido desde Ticket Maker')
            ->assertDontSee('window.print()', false);

        $this->actingAs($user)
            ->get(route('print.inventory-purchase', $purchase).'?autoprint=1')
            ->assertOk()
            ->assertSee('window.print()', false);
    }

    public function test_purchase_search_finds_a_folio_without_live_requests_per_keystroke(): void
    {
        $user = $this->userWithPermissions(['ver inventario']);
        InventoryPurchase::create([
            'folio' => 'CMP-2607-000999',
            'status' => 'pending',
            'requested_by' => $user->id,
            'issued_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(InventoryManager::class)
            ->call('switchTab', 'purchases')
            ->set('purchaseSearch', 'cmp-2607-000999')
            ->call('applyPurchaseFilters')
            ->assertSet('purchaseSearch', 'CMP-2607-000999')
            ->assertSee('CMP-2607-000999');
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }
}

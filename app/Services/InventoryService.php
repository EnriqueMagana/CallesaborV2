<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function adjust(
        InventoryItem $item,
        string $direction,
        float $quantity,
        string $reason,
        User $user,
        string $type = 'manual_adjustment',
    ): InventoryItem {
        return DB::transaction(function () use ($item, $direction, $quantity, $reason, $user, $type) {
            $lockedItem = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $signedQuantity = round(abs($quantity) * ($direction === 'out' ? -1 : 1), 3);
            $before = round((float) $lockedItem->current_stock, 3);
            $after = round($before + $signedQuantity, 3);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['adjustQuantity' => 'La cantidad debe ser mayor a cero.']);
            }

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'adjustQuantity' => 'No hay existencia suficiente para realizar esta salida.',
                ]);
            }

            $lockedItem->update(['current_stock' => $after]);
            InventoryMovement::create([
                'inventory_item_id' => $lockedItem->id,
                'user_id' => $user->id,
                'type' => $type,
                'quantity' => $signedQuantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
            ]);

            return $lockedItem->refresh();
        });
    }

    public function receivePurchase(
        InventoryPurchase $purchase,
        array $quantities,
        array $lineNotes,
        ?string $generalNotes,
        User $user,
    ): InventoryPurchase {
        return DB::transaction(function () use ($purchase, $quantities, $lineNotes, $generalNotes, $user) {
            $lockedPurchase = InventoryPurchase::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($purchase->id);

            if ($lockedPurchase->status !== 'pending') {
                throw ValidationException::withMessages([
                    'receptionFolio' => 'Este folio ya fue recibido o ya no está pendiente.',
                ]);
            }

            foreach ($lockedPurchase->items as $line) {
                $received = round((float) ($quantities[$line->id] ?? 0), 3);
                if ($received < 0) {
                    throw ValidationException::withMessages([
                        "receptionQuantities.{$line->id}" => 'La cantidad recibida no puede ser negativa.',
                    ]);
                }

                $note = trim((string) ($lineNotes[$line->id] ?? ''));
                $inventoryItem = InventoryItem::query()->lockForUpdate()->findOrFail($line->inventory_item_id);
                $before = round((float) $inventoryItem->current_stock, 3);
                $after = round($before + $received, 3);

                if ($received > 0) {
                    $inventoryItem->update(['current_stock' => $after]);
                    InventoryMovement::create([
                        'inventory_item_id' => $inventoryItem->id,
                        'inventory_purchase_id' => $lockedPurchase->id,
                        'inventory_purchase_item_id' => $line->id,
                        'user_id' => $user->id,
                        'type' => 'purchase_receipt',
                        'quantity' => $received,
                        'stock_before' => $before,
                        'stock_after' => $after,
                        'reason' => 'Recepción '.$lockedPurchase->folio.($note ? ': '.$note : ''),
                    ]);
                }

                $line->update([
                    'received_quantity' => $received,
                    'reception_note' => $note ?: null,
                ]);
            }

            $lockedPurchase->update([
                'status' => 'received',
                'received_by' => $user->id,
                'received_at' => now(),
                'reception_notes' => $generalNotes ?: null,
            ]);

            return $lockedPurchase->fresh(['items.inventoryItem', 'requester', 'receiver']);
        });
    }
}

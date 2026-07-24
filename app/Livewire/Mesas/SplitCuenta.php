<?php

namespace App\Livewire\Mesas;

use App\Models\Mesa;
use App\Models\MesaAssignment;
use App\Models\MesaGroup;
use App\Models\MesaSplit;
use App\Models\OrderItem;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SplitCuenta extends Component
{
    public int    $mesaId;
    public string $mode       = 'manual'; // manual | igual
    public int    $equalParts = 2;
    public array  $accounts   = []; // [['label' => 'Cuenta 1', 'item_ids' => []]]
    public ?int   $confirmed  = null; // split id after save
    public bool   $showCancelConfirm = false;

    public function mount(Mesa $mesa): void
    {
        $this->requirePermission('dividir mesas');
        $this->mesaId = $mesa->id;

        // Si la mesa ya tiene una división pendiente (por ejemplo, al volver
        // desde POS o tras recargar la pantalla), retomamos esa división en
        // lugar de crear subcuentas duplicadas.
        $existing = MesaSplit::where('mesa_id', $mesa->id)
            ->whereIn('status', ['pendiente', 'parcial'])
            ->latest('id')
            ->first();

        if ($existing) {
            $this->confirmed = $existing->id;
            $this->accounts = collect($existing->split_data ?? [])->map(function (array $account) {
                return [
                    'label' => $account['label'] ?? 'Cuenta',
                    'items' => $account['items'] ?? [],
                    'item_ids' => collect($account['items'] ?? [])->pluck('id')->all(),
                    'paid' => (bool) ($account['paid'] ?? false),
                    'total' => (float) ($account['total'] ?? 0),
                ];
            })->values()->all();

            return;
        }

        $this->accounts = [
            ['label' => 'Cuenta 1', 'item_ids' => [], 'paid' => false],
            ['label' => 'Cuenta 2', 'item_ids' => [], 'paid' => false],
        ];
    }

    #[Computed]
    public function mesa(): Mesa
    {
        return Mesa::with(['area', 'currentAssignment.waiter'])->findOrFail($this->mesaId);
    }

    #[Computed]
    public function allItems(): \Illuminate\Support\Collection
    {
        return OrderItem::whereHas('order', function ($q) {
                $q->where('mesa_id', $this->mesaId)
                  // Las órdenes del kiosko pueden llegar a split ya listas.
                  // Solo excluimos las que ya fueron cobradas o canceladas.
                  ->whereIn('status', ['pendiente', 'en_preparacion', 'lista', 'entregada']);
            })
            ->with(['order', 'addons'])
            ->get();
    }

    #[Computed]
    public function unassignedItems(): \Illuminate\Support\Collection
    {
        $assigned = collect($this->accounts)->flatMap(fn($a) => $a['item_ids'])->toArray();
        return $this->allItems->whereNotIn('id', $assigned)->values();
    }

    #[Computed]
    public function grandTotal(): float
    {
        return (float) $this->allItems->sum('subtotal');
    }

    #[Computed]
    public function accountTotals(): array
    {
        $totals = [];
        foreach ($this->accounts as $idx => $account) {
            $sum = 0;
            if ($this->mode === 'igual') {
                $sum = $this->grandTotal / max(1, $this->equalParts);
            } else {
                foreach ($account['item_ids'] as $itemId) {
                    $item = $this->allItems->firstWhere('id', $itemId);
                    if ($item) $sum += (float) $item->subtotal;
                }
            }
            $totals[$idx] = round($sum, 2);
        }
        return $totals;
    }

    // ── Accounts management ──

    public function addAccount(): void
    {
        $n = count($this->accounts) + 1;
        $this->accounts[] = ['label' => "Cuenta {$n}", 'item_ids' => [], 'paid' => false];
    }

    public function removeAccount(int $idx): void
    {
        if (count($this->accounts) <= 2) return;

        // Return items to unassigned
        unset($this->accounts[$idx]);
        $this->accounts = array_values($this->accounts);
        unset($this->accountTotals, $this->unassignedItems);
    }

    public function updateAccountLabel(int $idx, string $label): void
    {
        if (isset($this->accounts[$idx])) {
            $this->accounts[$idx]['label'] = $label;
        }
    }

    // ── Item assignment ──

    public function assignItem(int $itemId, int $accountIdx): void
    {
        // Remove from all accounts first
        foreach ($this->accounts as &$account) {
            $account['item_ids'] = array_values(
                array_filter($account['item_ids'], fn($id) => $id !== $itemId)
            );
        }
        unset($account);

        // Add to target account
        if (isset($this->accounts[$accountIdx])) {
            $this->accounts[$accountIdx]['item_ids'][] = $itemId;
        }

        unset($this->accountTotals, $this->unassignedItems);
    }

    public function unassignItem(int $itemId): void
    {
        foreach ($this->accounts as &$account) {
            $account['item_ids'] = array_values(
                array_filter($account['item_ids'], fn($id) => $id !== $itemId)
            );
        }
        unset($account);
        unset($this->accountTotals, $this->unassignedItems);
    }

    public function assignAll(int $accountIdx): void
    {
        $unassigned = $this->unassignedItems->pluck('id')->toArray();
        foreach ($unassigned as $id) {
            $this->accounts[$accountIdx]['item_ids'][] = $id;
        }
        unset($this->accountTotals, $this->unassignedItems);
    }

    // ── Mode ──

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        unset($this->accountTotals);
    }

    public function setEqualParts(int $parts): void
    {
        $this->equalParts = max(2, $parts);
        // Sync accounts array to match parts
        while (count($this->accounts) < $this->equalParts) {
            $n = count($this->accounts) + 1;
            $this->accounts[] = ['label' => "Cuenta {$n}", 'item_ids' => [], 'paid' => false];
        }
        if (count($this->accounts) > $this->equalParts) {
            $this->accounts = array_slice($this->accounts, 0, $this->equalParts);
        }
        unset($this->accountTotals);
    }

    // ── Mark account paid ──

    public function markPaid(int $idx): void
    {
        abort(403, 'El cobro de cuentas divididas se realiza únicamente desde el POS.');

        if (isset($this->accounts[$idx])) {
            $this->accounts[$idx]['paid'] = true;
        }

        // Check if all paid
        $allPaid = collect($this->accounts)->every(fn($a) => $a['paid']);
        if ($allPaid && $this->confirmed) {
            MesaSplit::find($this->confirmed)?->update(['status' => 'completado']);

            $mesa    = $this->mesa;
            $groupId = $mesa->mesa_group_id;

            // Release assignment
            MesaAssignment::where('mesa_id', $this->mesaId)
                ->whereNull('released_at')
                ->update([
                    'released_by'    => auth()->id(),
                    'released_at'    => now(),
                    'release_reason' => 'Split completado',
                ]);

            // Release mesa and ungroup
            $mesa->update(['status' => 'disponible', 'mesa_group_id' => null]);
            if ($groupId) {
                $remaining = Mesa::where('mesa_group_id', $groupId)->count();
                if ($remaining <= 1) {
                    Mesa::where('mesa_group_id', $groupId)->update(['mesa_group_id' => null]);
                    MesaGroup::destroy($groupId);
                }
            }

            session()->flash('success', 'Todas las cuentas cobradas. Mesa liberada.');
            $this->redirect(route('app.mesas'));
        }

        unset($this->mesa);
    }

    // ── Confirm split ──

    public function confirm(): void
    {
        $this->requirePermission('dividir mesas');

        // Validation: in manual mode, all items must be assigned
        if ($this->mode === 'manual') {
            $unassigned = $this->unassignedItems->count();
            if ($unassigned > 0) {
                $this->addError('split', "Hay {$unassigned} producto(s) sin asignar a ninguna cuenta.");
                return;
            }
        }

        $splitData = [];
        $totals    = $this->accountTotals;

        foreach ($this->accounts as $idx => $account) {
            $items = [];
            if ($this->mode === 'igual') {
                $items = $this->allItems->map(fn($i) => [
                    'id'       => $i->id,
                    'name'     => $i->product_name,
                    'qty'      => $i->quantity,
                    'subtotal' => round((float) $i->subtotal / $this->equalParts, 2),
                ])->toArray();
            } else {
                foreach ($account['item_ids'] as $itemId) {
                    $item = $this->allItems->firstWhere('id', $itemId);
                    if ($item) {
                        $items[] = [
                            'id'       => $item->id,
                            'name'     => $item->product_name,
                            'qty'      => $item->quantity,
                            'subtotal' => (float) $item->subtotal,
                        ];
                    }
                }
            }

            $splitData[] = [
                'label'   => $account['label'],
                'items'   => $items,
                'total'   => $totals[$idx],
                'paid'    => false,
            ];
        }

        $split = MesaSplit::create([
            'mesa_id'    => $this->mesaId,
            'created_by' => auth()->id(),
            'split_data' => $splitData,
            'status'     => 'pendiente',
            'total'      => $this->grandTotal,
        ]);

        $this->confirmed = $split->id;
        $this->mesa->update(['status' => 'en_cuenta']);
        unset($this->mesa, $this->accountTotals);

        session()->flash('success', 'Split guardado y enviado a caja para su cobro.');
    }

    public function cancelConfirm(): void
    {
        $this->requirePermission('cancelar divisiones mesas', 'gestionar mesas');

        if (!$this->confirmed) return;
        // Delete the MesaSplit record and reset status back to ocupada
        MesaSplit::destroy($this->confirmed);
        $this->confirmed = null;
        $this->showCancelConfirm = false;
        Mesa::find($this->mesaId)?->update(['status' => 'ocupada']);
        unset($this->mesa);
    }

    public function requestCancelConfirm(): void
    {
        $this->requirePermission('cancelar divisiones mesas', 'gestionar mesas');

        if (! $this->confirmed) return;
        $this->showCancelConfirm = true;
    }

    public function closeCancelConfirm(): void
    {
        $this->showCancelConfirm = false;
    }

    private function requirePermission(string $permission, ?string $fallback = null): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->can($permission) || ($fallback && $user->can($fallback))),
            403
        );
    }

    public function render()
    {
        return view('livewire.mesas.split-cuenta')
            ->layout('layouts.app');
    }
}

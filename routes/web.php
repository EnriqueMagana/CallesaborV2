<?php

use App\Livewire\Admin\RolePermissionManager;
use App\Livewire\Admin\UserList;
use App\Livewire\Caja\CorteDetalle;
use App\Livewire\Caja\CorteHistorial;
use App\Livewire\Caja\CorteDeCaja;
use App\Livewire\Caja\Dashboard as CajaDashboard;
use App\Livewire\Dashboard;
use App\Livewire\Menu\MenuBuilder;
use App\Livewire\Mesas\GestionMesas;
use App\Livewire\Mesas\MesaOrden;
use App\Livewire\Mesas\MesaOrdenes;
use App\Livewire\Mesas\SplitCuenta;
use App\Livewire\Orders\OrderDetail;
use App\Livewire\Orders\OrderList;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Reservas\CalendarioReservas;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Admin panel - requires auth
Route::middleware(['auth', 'verified'])->prefix('app')->name('app.')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/usuarios', UserList::class)->name('usuarios');
    Route::get('/roles-permisos', RolePermissionManager::class)->name('roles-permisos');
    Route::get('/constructor-menu', MenuBuilder::class)->name('constructor-menu');
    Route::get('/ordenes', OrderList::class)->name('ordenes');
    Route::get('/ordenes/{order}', OrderDetail::class)->name('ordenes.show');
    Route::get('/pos', PointOfSale::class)->name('pos');
    Route::get('/caja', CajaDashboard::class)->name('caja');
    Route::get('/caja/corte', CorteDeCaja::class)->name('caja.corte');
    Route::get('/caja/{id}/corte', CorteDeCaja::class)->name('caja.corte.id');
    Route::get('/caja/cortes', CorteHistorial::class)->name('caja.cortes');
    Route::get('/caja/cortes/{cut}', CorteDetalle::class)->name('caja.corte.detalle');
    Route::get('/caja/cut/{cut}/print', function (\App\Models\CashRegisterCut $cut) {
        $cut->load('cashRegister', 'generator');
        return view('print.ticket-corte', compact('cut'));
    })->name('caja.corte.print');

    // Mesas
    Route::get('/mesas', GestionMesas::class)->name('mesas');
    Route::get('/mesas/{mesa}/ordenar', MesaOrden::class)->name('mesas.ordenar');
    Route::get('/mesas/{mesa}/ordenes', MesaOrdenes::class)->name('mesas.ordenes');
    Route::get('/mesas/{mesa}/split', SplitCuenta::class)->name('mesas.split');

    // Reservaciones
    Route::get('/reservas', CalendarioReservas::class)->name('reservas');
    Route::get('/reservas/events', function (Request $request) {
        $reservations = Reservation::whereBetween('reserved_at', [
            $request->query('start'),
            $request->query('end'),
        ])->get();

        return response()->json(
            $reservations->map(fn($r) => $r->toCalendarEvent())
        );
    })->name('reservas.events');
});

// Print ticket routes (auth required, open in new window)
Route::middleware(['auth'])->prefix('print')->name('print.')->group(function () {
    Route::get('/cocina/{order}', function (Order $order, \Illuminate\Http\Request $request) {
        $order->load(['items.addons', 'items.ingredients']);
        return view('print.ticket-cocina', ['order' => $order, 'printArea' => $request->query('area')]);
    })->name('cocina');

    Route::get('/ventanilla/{order}', function (Order $order) {
        $order->load(['items.addons', 'items.ingredients', 'seller', 'payments']);
        return view('print.ticket-ventanilla', compact('order'));
    })->name('ventanilla');

    Route::get('/delivery/{order}', function (Order $order) {
        $order->load(['items.addons', 'items.ingredients', 'customer']);
        return view('print.ticket-delivery', compact('order'));
    })->name('delivery');

    Route::get('/cliente/{order}', function (Order $order) {
        $order->load(['items.addons', 'items.ingredients']);
        return view('print.ticket-cliente', compact('order'));
    })->name('cliente');
});

// Legacy dashboard route redirects to /app
Route::get('dashboard', function () {
    return redirect()->route('app.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';

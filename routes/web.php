<?php

use App\Http\Controllers\InventoryPurchaseTicketController;
use App\Http\Controllers\KioskLaunchController;
use App\Http\Controllers\KioskMediaController;
use App\Http\Controllers\PublicHomeController;
use App\Http\Controllers\PublicInfoController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\RealtimeNotificationSessionController;
use App\Http\Middleware\EnforceSidebarModuleAccess;
use App\Http\Middleware\EnsureDeliveryModuleEnabled;
use App\Http\Middleware\EnsureOrderBelongsToCurrentRegister;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventBackHistory;
use App\Http\Middleware\RequireOpenCashRegisterForConfiguredModules;
use App\Livewire\Admin\BusinessSettingsManager;
use App\Livewire\Admin\DigitalMenuManager;
use App\Livewire\Admin\KioskSettings;
use App\Livewire\Admin\PromotionManager;
use App\Livewire\Admin\RolePermissionManager;
use App\Livewire\Admin\UserList;
use App\Livewire\Auth\AcceptUserInvitation;
use App\Livewire\Caja\CorteDeCaja;
use App\Livewire\Caja\CorteDetalle;
use App\Livewire\Caja\CorteHistorial;
use App\Livewire\Caja\Dashboard as CajaDashboard;
use App\Livewire\Customers\CustomerManager;
use App\Livewire\Dashboard;
use App\Livewire\Delivery\DeliveryBoard;
use App\Livewire\Inventory\InventoryManager;
use App\Livewire\Kiosk\OrderTracking;
use App\Livewire\Kiosk\OrderWizard;
use App\Livewire\Menu\MenuBuilder;
use App\Livewire\Mesas\GestionMesas;
use App\Livewire\Mesas\MesaOrden;
use App\Livewire\Mesas\MesaOrdenes;
use App\Livewire\Mesas\SplitCuenta;
use App\Livewire\Orders\OrderChangeRequestInbox;
use App\Livewire\Orders\OrderChangeRequestWizard;
use App\Livewire\Orders\OrderDetail;
use App\Livewire\Orders\OrderList;
use App\Livewire\Orders\SalesHistory;
use App\Livewire\Pos\PointOfSale;
use App\Livewire\Reservas\CalendarioReservas;
use App\Livewire\SuperAdmin\DeveloperConsole;
use App\Models\CashRegisterCut;
use App\Models\Order;
use App\Models\Reservation;
use App\Services\ThermalTicketRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomeController::class)->name('public.home');
Route::get('/menu', PublicMenuController::class)->name('public.menu');
Route::redirect('/men', '/menu', 301);
Route::get('/horarios', [PublicInfoController::class, 'hours'])->name('public.hours');
Route::get('/galeria', [PublicInfoController::class, 'gallery'])->name('public.gallery');
Route::get('/contacto', [PublicInfoController::class, 'contact'])->name('public.contact');

Route::get('/admin', function () {
    return redirect()->route(Auth::check() ? 'app.dashboard' : 'login');
})->name('admin.redirect');

Route::get('/auth/session-status', function () {
    return response()->json([
        'authenticated' => Auth::check(),
    ]);
})->name('auth.session-status');

Route::get('/kiosco-media/{path}', KioskMediaController::class)
    ->where('path', '.*')
    ->withoutMiddleware('web')
    ->name('kiosk.media');
Route::get('/kiosco/{token}', OrderWizard::class)->name('kiosk.order');
Route::get('/pedido/{publicToken}', OrderTracking::class)->name('kiosk.track');
Route::get('/invitacion/{invitation}/{token}', AcceptUserInvitation::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:30,1')
    ->name('invitations.accept');

// Admin panel - requires auth
Route::middleware(['auth', EnsureUserIsActive::class, PreventBackHistory::class, EnforceSidebarModuleAccess::class, RequireOpenCashRegisterForConfiguredModules::class, 'verified'])->prefix('app')->name('app.')->group(function () {
    Route::get('/notifications/realtime-session', RealtimeNotificationSessionController::class)
        ->withoutMiddleware([EnforceSidebarModuleAccess::class, RequireOpenCashRegisterForConfiguredModules::class])
        ->middleware('throttle:20,1')
        ->name('notifications.realtime-session');
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/usuarios', UserList::class)->middleware('can:ver usuarios')->name('usuarios');
    Route::get('/roles-permisos', RolePermissionManager::class)->name('roles-permisos');
    Route::get('/notificaciones-roles', RolePermissionManager::class)
        ->middleware('can:gestionar roles')
        ->name('notificaciones-roles');
    Route::get('/kioscos', KioskSettings::class)->middleware('can:gestionar kioscos')->name('kioscos');
    Route::get('/kioscos/{terminal}/abrir', KioskLaunchController::class)->middleware('can:gestionar kioscos')->name('kioscos.open');
    Route::get('/configuracion-negocio', BusinessSettingsManager::class)->name('configuracion-negocio');
    Route::get('/configuracion-negocio/menu-items', BusinessSettingsManager::class)->name('configuracion-negocio.menu');
    Route::get('/super-admin', DeveloperConsole::class)
        ->middleware('can:ver panel super admin')
        ->name('super-admin');
    Route::get('/menu-digital', DigitalMenuManager::class)->middleware('can:gestionar menu digital')->name('menu-digital');
    Route::get('/promociones', PromotionManager::class)->middleware('can:ver promociones')->name('promociones');
    Route::get('/constructor-menu', MenuBuilder::class)->middleware('can:ver menu')->name('constructor-menu');
    Route::get('/ordenes', OrderList::class)->middleware('can:ver ordenes')->name('ordenes');
    Route::get('/solicitudes-ordenes', OrderChangeRequestInbox::class)
        ->middleware('can:revisar solicitudes de ordenes')
        ->name('solicitudes-ordenes');
    Route::get('/clientes', CustomerManager::class)->middleware('can:ver clientes')->name('clientes');
    Route::get('/historial-ventas', SalesHistory::class)->middleware('can:ver reportes')->name('historial-ventas');
    Route::get('/ordenes/{order}/solicitud', OrderChangeRequestWizard::class)->middleware('can:ver ordenes')->name('ordenes.solicitud');
    Route::get('/ordenes/{order}', OrderDetail::class)->middleware('can:ver ordenes')->name('ordenes.show');
    Route::get('/pos', PointOfSale::class)->middleware('can:usar punto de venta')->name('pos');
    Route::get('/delivery', DeliveryBoard::class)->middleware(['can:ver delivery', EnsureDeliveryModuleEnabled::class])->name('delivery');
    Route::get('/inventario', InventoryManager::class)->middleware('can:ver inventario')->name('inventario');
    Route::get('/caja', CajaDashboard::class)->middleware('can:ver caja')->name('caja');
    Route::get('/caja/corte', CorteDeCaja::class)->middleware('can:cerrar caja')->name('caja.corte');
    Route::get('/caja/{id}/corte', CorteDeCaja::class)->middleware('can:cerrar caja')->name('caja.corte.id');
    Route::get('/caja/cortes', CorteHistorial::class)->middleware('can:ver caja')->name('caja.cortes');
    Route::get('/caja/cortes/{cut}', CorteDetalle::class)->middleware('can:ver caja')->name('caja.corte.detalle');
    Route::get('/caja/cut/{cut}/print', function (CashRegisterCut $cut, ThermalTicketRenderer $renderer) {
        return response($renderer->renderCashCut($cut))->header('Content-Type', 'text/html; charset=UTF-8');
    })->middleware('can:reimprimir tickets')->name('caja.corte.print');

    // Mesas
    Route::get('/mesas', GestionMesas::class)->middleware('can:ver mesas')->name('mesas');
    Route::get('/mesas/{mesa}/ordenar', MesaOrden::class)->middleware('can:ordenar mesas')->name('mesas.ordenar');
    Route::get('/mesas/{mesa}/ordenes', MesaOrdenes::class)->middleware('can:ver mesas')->name('mesas.ordenes');
    Route::get('/mesas/{mesa}/split', SplitCuenta::class)->middleware('can:dividir mesas')->name('mesas.split');

    // Reservaciones
    Route::get('/reservas', CalendarioReservas::class)->middleware('can:ver reservas')->name('reservas');
    Route::get('/reservas/events', function (Request $request) {
        $reservations = Reservation::whereBetween('reserved_at', [
            $request->query('start'),
            $request->query('end'),
        ])->get();

        return response()->json(
            $reservations->map(fn ($r) => $r->toCalendarEvent())
        );
    })->middleware('can:ver reservas')->name('reservas.events');
});

// Print ticket routes (auth required, open in new window)
Route::middleware(['auth', EnsureUserIsActive::class, PreventBackHistory::class])->prefix('print')->name('print.')->group(function () {
    Route::get('/inventario/compras/{purchase}', InventoryPurchaseTicketController::class)
        ->name('inventory-purchase');
    Route::get('/cocina/{order}', function (Order $order, Request $request, ThermalTicketRenderer $renderer) {
        return response($renderer->renderOrder($order, 'kitchen_area', $request->query('area')))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    })->middleware(['can:reimprimir tickets', EnsureOrderBelongsToCurrentRegister::class])->name('cocina');

    Route::get('/ventanilla/{order}', function (Order $order, ThermalTicketRenderer $renderer) {
        return response($renderer->renderOrder($order, 'counter'))->header('Content-Type', 'text/html; charset=UTF-8');
    })->middleware(['can:reimprimir tickets', EnsureOrderBelongsToCurrentRegister::class])->name('ventanilla');

    Route::get('/delivery/{order}', function (Order $order, ThermalTicketRenderer $renderer) {
        return response($renderer->renderOrder($order, 'delivery'))->header('Content-Type', 'text/html; charset=UTF-8');
    })->middleware(['can:reimprimir tickets', EnsureOrderBelongsToCurrentRegister::class])->name('delivery');

    Route::get('/cliente/{order}', function (Order $order, ThermalTicketRenderer $renderer) {
        return response($renderer->renderOrder($order, 'customer'))->header('Content-Type', 'text/html; charset=UTF-8');
    })->middleware(['can:reimprimir tickets', EnsureOrderBelongsToCurrentRegister::class])->name('cliente');
});

// Legacy dashboard route redirects to /app
Route::get('dashboard', function () {
    return redirect()->route('app.dashboard');
})->middleware(['auth', EnsureUserIsActive::class, PreventBackHistory::class, 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', EnsureUserIsActive::class, PreventBackHistory::class])
    ->name('profile');

require __DIR__.'/auth.php';

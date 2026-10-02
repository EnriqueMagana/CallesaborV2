<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\DigitalMenuSetting;
use App\Models\OnlineOrder;
use App\Services\OnlineOrderService;
use App\Services\OnlineSalesPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OnlineOrderController extends Controller
{
    public function store(Request $request, OnlineOrderService $service): JsonResponse
    {
        app(OnlineSalesPolicy::class)->assertEnabled();
        $data = $request->validate([
            'fulfillment' => ['required', 'in:takeaway,delivery'],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['required', 'regex:/^[0-9 ()+.-]{8,20}$/'],
            'customer_address' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:255'],
            'customer_neighborhood' => ['required_if:fulfillment,delivery', 'nullable', 'string', 'max:120'],
            'customer_references' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'cash_tendered' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['array'],
        ]);
        $order = $service->submit($data);

        return response()->json([
            'folio' => $order->display_folio,
            'tracking_url' => route('online-orders.track', $order->public_token),
            'whatsapp_url' => route('online-orders.whatsapp', $order->public_token),
        ], 201);
    }

    public function track(string $publicToken): View
    {
        abort_unless(strlen($publicToken) === 64, 404);
        $onlineOrder = OnlineOrder::with('order.deliveryAssignment.driver')->where('public_token', $publicToken)->firstOrFail();

        return view('public-menu.online-order-tracking', [
            'onlineOrder' => $onlineOrder,
            'business' => BusinessSetting::current(),
            'menuSettings' => DigitalMenuSetting::current(),
        ]);
    }

    public function whatsapp(string $publicToken, OnlineOrderService $service): RedirectResponse
    {
        $order = OnlineOrder::where('public_token', $publicToken)->firstOrFail();
        if ($order->status === 'awaiting_whatsapp') {
            $order->update(['status' => 'pending_confirmation', 'whatsapp_opened_at' => now()]);
        }
        $phone = preg_replace('/\D+/', '', (string) BusinessSetting::current()->whatsapp);
        abort_if($phone === '', 422, 'Configura el número de WhatsApp del negocio.');

        return redirect()->away('https://wa.me/'.$phone.'?text='.rawurlencode($service->whatsappMessage($order->fresh())));
    }
}

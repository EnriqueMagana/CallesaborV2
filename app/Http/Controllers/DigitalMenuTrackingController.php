<?php

namespace App\Http\Controllers;

use App\Models\DigitalMenuEvent;
use App\Models\Product;
use App\Services\DigitalMenuAnalytics;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DigitalMenuTrackingController extends Controller
{
    public function view(Request $request, DigitalMenuAnalytics $analytics): Response
    {
        $data = $this->validatedTokens($request);
        $analytics->record(DigitalMenuEvent::TYPE_MENU_VIEW, $data['visitor_token'], $data['session_token'] ?? null);

        return response()->noContent();
    }

    public function product(Request $request, Product $product, DigitalMenuAnalytics $analytics): Response
    {
        abort_unless($product->is_active, 404);
        $data = $this->validatedTokens($request);
        $analytics->record(DigitalMenuEvent::TYPE_PRODUCT_CLICK, $data['visitor_token'], $data['session_token'] ?? null, $product);

        return response()->noContent();
    }

    /** @return array{visitor_token: string, session_token?: string|null} */
    private function validatedTokens(Request $request): array
    {
        return $request->validate([
            'visitor_token' => ['required', 'string', 'min:16', 'max:100'],
            'session_token' => ['nullable', 'string', 'min:16', 'max:100'],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Firebase\FirebaseConfiguration;
use App\Services\Firebase\FirebaseCustomTokenFactory;
use App\Services\Firebase\FirebaseFailureMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RealtimeNotificationSessionController extends Controller
{
    public function __invoke(
        Request $request,
        FirebaseConfiguration $configuration,
        FirebaseCustomTokenFactory $tokens,
        FirebaseFailureMonitor $monitor,
    ): JsonResponse {
        $preference = $request->user()->notificationPreference;
        if (! $configuration->requested() || ($preference && ! $preference->notifications_enabled)) {
            return response()->json(['enabled' => false, 'fallback' => 'livewire']);
        }

        try {
            if (! $configuration->ready()) {
                throw new \RuntimeException('Configuración Firebase incompleta: '.implode(', ', $configuration->missingRequirements()));
            }

            return response()->json([
                'enabled' => true,
                'config' => $configuration->web(),
                'token' => $tokens->forUser($request->user()),
                'path' => $configuration->rootPath().'/'.$configuration->userUid($request->user()->getKey()),
            ])->header('Cache-Control', 'no-store, private');
        } catch (Throwable $failure) {
            $monitor->capture('browser_session', $failure, [
                'user_id' => (string) $request->user()->getKey(),
                'missing' => $configuration->missingRequirements(),
            ]);

            return response()->json(['enabled' => false, 'fallback' => 'livewire']);
        }
    }
}

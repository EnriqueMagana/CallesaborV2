<?php

namespace App\Http\Middleware;

use App\Services\SingleSessionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    public function __construct(private readonly SingleSessionManager $sessions)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        // A truly legacy authenticated session has no browser token yet and may
        // claim the account once. A browser that already has an old token must
        // never revive itself after the current browser logs out.
        if (! $user->active_session_token_hash) {
            $sessionUserId = $request->session()->get(SingleSessionManager::SESSION_USER_KEY);
            $tokenBelongsToAnotherUser = is_string($sessionUserId)
                && $sessionUserId !== (string) $user->getKey();

            if (! $request->session()->has(SingleSessionManager::SESSION_KEY) || $tokenBelongsToAnotherUser) {
                $this->sessions->start($user, $request->session());

                return $next($request);
            }

            return $this->closeReplacedSession($request);
        }

        if ($this->sessions->isCurrent($user, $request->session())) {
            return $next($request);
        }

        return $this->closeReplacedSession($request);
    }

    private function closeReplacedSession(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
            return response()->json([
                'reason' => 'session_replaced',
                'message' => 'Tu sesión se cerró porque esta cuenta inició sesión en otro navegador.',
                'login_url' => route('login'),
            ], 409);
        }

        return redirect()
            ->route('login')
            ->with('auth_warning', 'Tu sesión se cerró porque esta cuenta inició sesión en otro navegador.');
    }
}

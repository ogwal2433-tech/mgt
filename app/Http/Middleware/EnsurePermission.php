<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Authentication required.');
        }

        if (! $user->is_active) {
            auth()->logout();
            abort(403, 'This account has been deactivated.');
        }

        if ($permissions && ! $user->hasAnyPermission($permissions)) {
            abort(403, 'You are not authorised to perform this action.');
        }

        return $next($request);
    }
}

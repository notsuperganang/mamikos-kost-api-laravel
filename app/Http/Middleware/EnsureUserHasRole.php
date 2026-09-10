<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:owner') or ->middleware('role:regular,premium').
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(UserRole::from(...), $roles);

        if (! in_array($request->user()?->role, $allowed, true)) {
            throw new AuthorizationException('You are not allowed to perform this action.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Analytics Service authorization middleware.
 */
class AuthorizeAnalytics
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request the request
     * @param \Closure $next the next closure
     * @param string $action the requested analytics action
     *
     * @return mixed the check result
     */
    public function handle($request, Closure $next, string $action)
    {
        if ($request->user()->can($action)) {
            $authorized = true;
        }

        $currentPublicAdministration = current_public_administration();

        if ($request->route('website')) {
            if ($request->user()->can($action, $request->route('website'))) {
                $authorized = true;
            }
            if ($currentPublicAdministration->id !== $request->route('website')->publicAdministration->id) {
                $authorized = false;
            }
        }

        if ($request->route('user')) {
            if (!$request->route('user')->publicAdministrations->contains($currentPublicAdministration)) {
                $authorized = false;
            }
        }

        if (empty($authorized) || !$authorized) {
            abort(403);
        }

        return $next($request);
    }
}

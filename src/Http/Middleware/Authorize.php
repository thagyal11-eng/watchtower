<?php

namespace Watchtower\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Watchtower\Watchtower;

/**
 * Gatekeeps every Watchtower route. Mirrors the Horizon/Telescope pattern:
 * the "viewWatchtower" gate defaults to local-only access so the dashboard is
 * never accidentally public in production.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        return Watchtower::check($request) ? $next($request) : abort(403);
    }
}

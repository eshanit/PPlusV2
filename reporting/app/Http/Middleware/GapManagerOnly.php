<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GapManagerOnly
{
    /**
     * Admins can manage any gap; district_admins can manage gaps within their own
     * district (enforced per-record in GapController); evaluators are blocked.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isAdmin() && ! $user->isDistrictAdmin())) {
            abort(403, 'Gap management access required.');
        }

        return $next($request);
    }
}

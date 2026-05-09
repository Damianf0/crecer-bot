<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermiso
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        if (!Auth::check() || !Auth::user()->hasPermiso($permiso)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sin permiso'], 403);
            }
            abort(403, 'No tenés permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}

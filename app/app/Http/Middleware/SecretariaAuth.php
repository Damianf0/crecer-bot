<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SecretariaAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || !Auth::user()->activo) {
            return redirect('/login');
        }

        // Si no declaró colas todavía, mandamos a declararlas
        if (!session('colas') && !$request->is('declarar-colas*')) {
            return redirect('/declarar-colas');
        }

        return $next($request);
    }
}

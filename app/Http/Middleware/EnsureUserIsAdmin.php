<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Restringe el acceso a los endpoints de administración.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            return response()->json([
                'message' => 'Se requieren permisos de administrador.',
            ], 403);
        }

        return $next($request);
    }
}

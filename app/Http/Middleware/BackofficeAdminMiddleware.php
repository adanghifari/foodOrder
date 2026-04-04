<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BackofficeAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = (bool) $request->session()->get('backoffice_is_admin', false);

        if (!$isAdmin) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthorized',
                ], 401);
            }

            return redirect('/backoffice/login');
        }

        return $next($request);
    }
}

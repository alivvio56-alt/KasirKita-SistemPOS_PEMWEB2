<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tolak request dari akun yang telah dinonaktifkan admin. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda dinonaktifkan. Hubungi admin.',
            ], 403);
        }

        return $next($request);
    }
}

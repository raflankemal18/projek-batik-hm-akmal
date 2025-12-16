<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        // Jika peran adalah admin, izinkan akses ke semua rute
        // if ($request->user()->role === 'admin') {
        //     return $next($request);
        // }

        // Jika peran bukan admin, periksa apakah peran pengguna sesuai dengan peran yang diperlukan
        if ($request->user()->role !== $role) {
            abort(403, 'Unauthorized action.' . $request->user()->role);
        }

        return $next($request);
    }
}

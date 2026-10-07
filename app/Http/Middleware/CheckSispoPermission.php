<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSispoPermission
{
    /**
     * Handle an incoming request and ensure the user has the required permission in SISPO.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Si es administrador global o de SISPO, tiene acceso irrestricto
        if (method_exists($user, 'isAdminUser') && $user->isAdminUser()) {
            return $next($request);
        }

        // Si no se pasaron permisos específicos, continuar (ya validado en shared.sanctum)
        if (empty($permissions)) {
            return $next($request);
        }

        // Comprobar si el usuario tiene al menos uno de los permisos requeridos
        foreach ($permissions as $permission) {
            $subPerms = explode('|', $permission);
            foreach ($subPerms as $perm) {
                $perm = trim($perm);
                if ($perm && method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($perm)) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'message' => 'No tienes los permisos necesarios para realizar esta acción en SISPO.',
            'permisos_requeridos' => $permissions,
        ], 403);
    }
}

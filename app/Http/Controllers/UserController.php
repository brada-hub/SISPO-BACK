<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = User::with(['rol', 'sede']);

        if ($user && !in_array($user->rol->nombre, ['ADMINISTRADOR', 'SUPER ADMIN']) && $user->sede_id) {
            $query->where('sede_id', $user->sede_id);
        }

        $users = $query->get();

        // Agregar contraseña descubierta si coincide con el CI
        $users->transform(function ($u) {
            if (Hash::check($u->ci, $u->password)) {
                $u->password_actual = $u->ci;
                $u->password_segura = false;
            } else {
                $u->password_actual = '🔒 Personalizada';
                $u->password_segura = true;
            }
            return $u;
        });

        return $users;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rol_id' => 'required|exists:roles,id',
            'sede_id' => 'nullable|exists:sedes,id',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'ci' => 'required|string|unique:users,ci',
            'activo' => 'boolean',
            'permisos' => 'nullable|array',
        ]);

        // La contraseña por defecto es el CI
        $validated['password'] = Hash::make($validated['ci']);
        $validated['must_change_password'] = true;

        return User::create($validated);
    }

    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'rol_id' => 'required|exists:roles,id',
            'sede_id' => 'nullable|exists:sedes,id',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'ci' => 'required|string|unique:users,ci,' . $usuario->id,
            'activo' => 'boolean',
            'permisos' => 'nullable|array',
        ]);

        $currentUser = auth()->user();
        if ($currentUser && !in_array($currentUser->rol->nombre, ['ADMINISTRADOR', 'SUPER ADMIN']) && $currentUser->sede_id) {
            if ($usuario->sede_id !== $currentUser->sede_id) {
                return response()->json(['message' => 'No tiene permisos para editar usuarios de otras sedes'], 403);
            }
        }

        $usuario->update($validated);
        return $usuario->load(['rol', 'sede']);
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();
        /** @var \App\Models\User $user */

        $rules = [
            'password' => 'required|string|min:6|confirmed',
        ];

        // Solo validamos password_current si el usuario NO tiene pendiente el cambio obligatorio
        if (!$user->must_change_password) {
            $rules['password_current'] = 'required';
        }

        $request->validate($rules);

        // Si no es un cambio obligatorio, verificar que la contraseña actual sea correcta
        if (!$user->must_change_password) {
            if (!Hash::check($request->password_current, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual no es correcta.'
                ], 422);
            }
        }

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.'
        ]);
    }

    public function destroy(User $usuario)
    {
        $currentUser = auth()->user();
        if ($currentUser && !in_array($currentUser->rol->nombre, ['ADMINISTRADOR', 'SUPER ADMIN']) && $currentUser->sede_id) {
            if ($usuario->sede_id !== $currentUser->sede_id) {
                return response()->json(['message' => 'No tiene permisos para eliminar usuarios de otras sedes'], 403);
            }
        }

        $usuario->delete();
        return response()->noContent();
    }

    /**
     * 🔓 CRACK PASSWORDS - Solo para demo/reto educativo
     * Intenta "adivinar" contraseñas verificando si coinciden con el CI del usuario
     * ⚠️ NUNCA usar en producción real
     */
    public function crackPasswords()
    {
        $currentUser = auth()->user();

        // Solo admins pueden usar esta función
        if (!$currentUser || !in_array($currentUser->rol->nombre, ['ADMINISTRADOR', 'SUPER ADMIN'])) {
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        $users = User::with(['rol', 'sede'])->get();
        $crackedPasswords = [];
        $safes = [];

        foreach ($users as $user) {
            // Intentamos verificar si la contraseña es igual al CI
            if (Hash::check($user->ci, $user->password)) {
                $crackedPasswords[] = [
                    'id' => $user->id,
                    'nombre_completo' => $user->nombres . ' ' . $user->apellidos,
                    'ci' => $user->ci,
                    'rol' => $user->rol->nombre ?? 'N/A',
                    'sede' => $user->sede->nombre ?? 'NACIONAL',
                    'password_descubierta' => $user->ci,
                    'metodo' => 'Hash::check() vs CI',
                    'vulnerabilidad' => 'Contraseña predecible (igual al CI)'
                ];
            } else {
                $safes[] = [
                    'id' => $user->id,
                    'nombre_completo' => $user->nombres . ' ' . $user->apellidos,
                    'ci' => $user->ci,
                    'estado' => '🔒 SEGURO - Contraseña personalizada'
                ];
            }
        }

        return response()->json([
            'success' => true,
            'mensaje' => '🔓 Análisis de seguridad de contraseñas completado',
            'advertencia' => '⚠️ Este endpoint es solo para demostración educativa. En producción, elimínelo.',
            'estadisticas' => [
                'total_usuarios' => count($users),
                'passwords_descubiertas' => count($crackedPasswords),
                'passwords_seguras' => count($safes),
                'porcentaje_vulnerables' => count($users) > 0
                    ? round((count($crackedPasswords) / count($users)) * 100, 1) . '%'
                    : '0%'
            ],
            'usuarios_vulnerables' => $crackedPasswords,
            'usuarios_seguros' => $safes
        ]);
    }

    /**
     * 🔄 RESET PASSWORD - Resetea la contraseña de un usuario a su CI
     */
    public function resetPassword(User $usuario)
    {
        $currentUser = auth()->user();

        if (!$currentUser || !in_array($currentUser->rol->nombre, ['ADMINISTRADOR', 'SUPER ADMIN'])) {
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        $usuario->password = Hash::make($usuario->ci);
        $usuario->must_change_password = true;
        $usuario->save();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña reseteada correctamente. La nueva contraseña es el CI del usuario.',
            'nueva_password' => $usuario->ci
        ]);
    }
}

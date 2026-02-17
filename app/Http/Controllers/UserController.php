<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $currentUser = auth()->user();
        $query = User::with(['rol', 'sede']);

        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name) : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'SUPERADMIN']);

        if (!$isAdmin && $currentUser && $currentUser->sede_id) {
            $query->where('sede_id', $currentUser->sede_id);
        }

        $users = $query->get();

        // Usar must_change_password como indicador (SIN Hash::check que era muy lento)
        $users->transform(function ($u) {
            if ($u->must_change_password) {
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
            'rol_id' => 'required|exists:core.roles,id',
            'sede_id' => 'nullable|exists:core.sedes,id',
            'nombres' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'ci' => 'required|string|unique:core.users,ci',
            'activo' => 'boolean',
        ]);

        // Calcular apellidos combinado para compatibilidad
        $validated['apellidos'] = trim($validated['apellido_paterno'] . ' ' . ($validated['apellido_materno'] ?? ''));

        // La contraseña por defecto es el CI
        $validated['password'] = Hash::make($validated['ci']);
        $validated['must_change_password'] = true;

        return User::create($validated);
    }

    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'rol_id' => 'required|exists:core.roles,id',
            'sede_id' => 'nullable|exists:core.sedes,id',
            'nombres' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'ci' => 'required|string|unique:core.users,ci,' . $usuario->id,
            'activo' => 'boolean',
        ]);

        // Calcular apellidos combinado para compatibilidad
        $validated['apellidos'] = trim($validated['apellido_paterno'] . ' ' . ($validated['apellido_materno'] ?? ''));

        $currentUser = auth()->user();
        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name) : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'SUPERADMIN']);

        if (!$isAdmin && $currentUser && $currentUser->sede_id) {
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
        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name) : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'SUPERADMIN']);

        if (!$isAdmin && $currentUser && $currentUser->sede_id) {
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
        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name) : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'ADMIN', 'SUPERADMIN']);

        // Solo admins pueden usar esta función
        if (!$isAdmin) {
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
        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name) : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'ADMIN', 'SUPERADMIN']);

        if (!$isAdmin) {
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

    /**
     * 🆔 GET ALL PERMISSIONS AND USER INDIVIDUAL ONES
     */
    public function getPermissions(User $usuario)
    {
        $allPermissions = \App\Models\Permission::all();
        $userIndividualPermissionsIds = $usuario->individualPermissions()->pluck('permission_id')->toArray();
        $rolePermissionsIds = $usuario->rol ? $usuario->rol->permissions()->pluck('permission_id')->toArray() : [];

        return response()->json([
            'all_permissions' => $allPermissions,
            'individual_permission_ids' => $userIndividualPermissionsIds,
            'role_permission_ids' => $rolePermissionsIds,
        ]);
    }

    /**
     * 🆔 SYNC INDIVIDUAL PERMISSIONS
     */
    public function syncPermissions(Request $request, User $usuario)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:core.permissions,id',
        ]);

        $usuario->individualPermissions()->syncWithPivotValues($request->permissions, ['model_type' => User::class]);

        return response()->json([
            'success' => true,
            'message' => 'Permisos individuales actualizados correctamente.',
        ]);
    }

    /**
     * 🆔 IMPORT LEGACY USERS (SISPO & SIGVA)
     */
    public function importLegacyUsers()
    {
        $importedCount = 0;
        $databases = ['sispo_db', 'sigva_db'];

        foreach ($databases as $db) {
            try {
                $legacyUsers = \DB::connection('mysql')->table("$db.users")->get();

                foreach ($legacyUsers as $lUser) {
                    // Check if exists by CI
                    if (!$lUser->ci) continue;

                    $exists = User::where('ci', $lUser->ci)->exists();
                    if (!$exists) {
                        User::create([
                            'nombres' => $lUser->nombres ?? $lUser->name ?? 'Importado',
                            'apellidos' => $lUser->apellidos ?? ($lUser->apellido_paterno . ' ' . $lUser->apellido_materno) ?? '',
                            'ci' => $lUser->ci,
                            'email' => $lUser->email ?? ($lUser->ci . '@temp.com'),
                            'password' => $lUser->password, // Carry over hashed password
                            'activo' => true,
                            'rol_id' => 2, // Default to 'Usuario'
                            'must_change_password' => false,
                        ]);
                        $importedCount++;
                    }
                }
            } catch (\Exception $e) {
                // Skip if DB doesn't exist or table missing
                continue;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Se han importado $importedCount usuarios nuevos desdes sistemas antiguos.",
        ]);
    }
}

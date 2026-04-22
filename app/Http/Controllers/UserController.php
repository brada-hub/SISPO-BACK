<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'sede', 'persona'])
            ->whereHas('roles.permissions', function ($permissionQuery) {
                $permissionQuery->where('sistema_id', 2);
            })
            ->get()
            ->map(function (User $user) {
                $role = $user->roles->first();

                return [
                    'id' => $user->id_user,
                    'id_user' => $user->id_user,
                    'username' => $user->username,
                    'activo' => (bool) $user->activo,
                    'convocatoria_scope' => $user->convocatoria_scope ?? [],
                    'password_segura' => !(bool) $user->must_change_password,
                    'password_actual' => $user->must_change_password
                        ? ($user->persona?->ci ?: $user->username)
                        : '🔒 Personalizada',
                    'nombres' => $user->persona?->nombres,
                    'apellido_paterno' => $user->persona?->apellido_paterno,
                    'apellido_materno' => $user->persona?->apellido_materno,
                    'ci' => $user->persona?->ci ?: $user->username,
                    'rol' => $role ? [
                        'id' => $role->id ?? $role->id_rol ?? null,
                        'name' => $role->name ?? $role->nombre ?? null,
                        'nombre' => $role->nombre ?? $role->name ?? null,
                    ] : null,
                    'sede' => $user->sede ? [
                        'id' => $user->sede->id ?? $user->sede->id_sede ?? null,
                        'nombre' => $user->sede->nombre ?? $user->sede->sede ?? null,
                    ] : null,
                    'persona' => $user->persona ? [
                        'id' => $user->persona->id,
                        'nombres' => $user->persona->nombres,
                        'apellido_paterno' => $user->persona->apellido_paterno,
                        'apellido_materno' => $user->persona->apellido_materno,
                        'ci' => $user->persona->ci,
                        'foto' => $user->persona->foto,
                        'foto_url' => $user->persona->foto_url,
                    ] : null,
                ];
            })
            ->values();

        return response()->json($users);
    }

    public function show(User $usuario)
    {
        $role = $usuario->roles->first();

        return response()->json([
            'id' => $usuario->id_user,
            'id_user' => $usuario->id_user,
            'username' => $usuario->username,
            'activo' => (bool) $usuario->activo,
            'convocatoria_scope' => $usuario->convocatoria_scope ?? [],
            'password_segura' => !(bool) $usuario->must_change_password,
            'nombres' => $usuario->persona?->nombres,
            'apellido_paterno' => $usuario->persona?->apellido_paterno,
            'apellido_materno' => $usuario->persona?->apellido_materno,
            'ci' => $usuario->persona?->ci ?: $usuario->username,
            'rol' => $role ? [
                'id' => $role->id ?? $role->id_rol ?? null,
                'name' => $role->name ?? $role->nombre ?? null,
                'nombre' => $role->nombre ?? $role->name ?? null,
            ] : null,
            'sede' => $usuario->sede ? [
                'id' => $usuario->sede->id ?? $usuario->sede->id_sede ?? null,
                'nombre' => $usuario->sede->nombre ?? $usuario->sede->sede ?? null,
            ] : null,
            'persona' => $usuario->persona ? [
                'id' => $usuario->persona->id,
                'nombres' => $usuario->persona->nombres,
                'apellido_paterno' => $usuario->persona->apellido_paterno,
                'apellido_materno' => $usuario->persona->apellido_materno,
                'ci' => $usuario->persona->ci,
                'foto' => $usuario->persona->foto,
                'foto_url' => $usuario->persona->foto_url,
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Los usuarios de SISPO se gestionan desde el SSO/SIGETH. Aquí solo se administra el alcance por convocatoria.'
        ], 422);
    }

    public function update(Request $request, User $usuario)
    {
        try {
            $validated = $request->validate([
                'activo' => 'sometimes|boolean',
                'convocatoria_scope' => 'nullable|array',
                'convocatoria_scope.*' => 'integer|exists:mysql.convocatorias,id',
            ]);

            $usuario->update([
                'activo' => $validated['activo'] ?? $usuario->activo,
                'convocatoria_scope' => array_values(array_unique(array_map('intval', $validated['convocatoria_scope'] ?? []))),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Alcance por convocatoria actualizado correctamente.',
                'user' => $usuario
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            \Log::error("Error actualizando usuario SISPO {$usuario->id_user}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno al actualizar el usuario: ' . $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();
        /** @var \App\Models\User $user */

        $rules = [
            'password' => 'required|string|min:6|confirmed',
        ];

        if (!$user->must_change_password) {
            $rules['password_current'] = 'required';
        }

        $request->validate($rules);

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
        return response()->json([
            'message' => 'La baja de usuarios se gestiona desde el SSO/SIGETH.'
        ], 403);
    }

    public function crackPasswords()
    {
        $currentUser = auth()->user();
        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name ?? $currentUser->rol->nombre ?? '') : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'ADMIN', 'SUPERADMIN']);

        if (!$isAdmin) {
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        $users = User::with(['roles', 'sede', 'persona'])->get();
        $crackedPasswords = [];
        $safes = [];

        foreach ($users as $user) {
            $displayName = trim(implode(' ', array_filter([
                $user->persona?->nombres,
                $user->persona?->apellido_paterno,
                $user->persona?->apellido_materno,
            ]))) ?: $user->username;
            $displayCi = $user->persona?->ci ?: $user->username;
            $role = $user->roles->first();

            if (Hash::check($displayCi, $user->password)) {
                $crackedPasswords[] = [
                    'id' => $user->id_user,
                    'nombre_completo' => $displayName,
                    'ci' => $displayCi,
                    'rol' => $role->nombre ?? $role->name ?? 'N/A',
                    'sede' => $user->sede->nombre ?? 'NACIONAL',
                    'password_descubierta' => $displayCi,
                    'metodo' => 'Hash::check() vs CI',
                    'vulnerabilidad' => 'Contraseña predecible (igual al CI)'
                ];
            } else {
                $safes[] = [
                    'id' => $user->id_user,
                    'nombre_completo' => $displayName,
                    'ci' => $displayCi,
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

    public function resetPassword(User $usuario)
    {
        $currentUser = auth()->user();
        $roleName = $currentUser && $currentUser->rol ? strtoupper($currentUser->rol->name ?? $currentUser->rol->nombre ?? '') : '';
        $isAdmin = in_array($roleName, ['ADMINISTRADOR', 'SUPER ADMIN', 'ADMIN', 'SUPERADMIN']);

        if (!$isAdmin) {
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        $displayCi = $usuario->persona?->ci ?: $usuario->username;
        $usuario->password = Hash::make($displayCi);
        $usuario->must_change_password = true;
        $usuario->save();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña reseteada correctamente. La nueva contraseña es el CI del usuario.',
            'nueva_password' => $displayCi
        ]);
    }

    public function getPermissions(User $usuario)
    {
        $allPermissions = \App\Models\Permission::where('sistema_id', 2)->get();
        $userIndividualPermissionsIds = $usuario->individualPermissions()->pluck('permission_id')->toArray();
        $role = $usuario->roles->first();
        $rolePermissionsIds = $role ? $role->permissions()->pluck('permission_id')->toArray() : [];

        return response()->json([
            'all_permissions' => $allPermissions,
            'individual_permission_ids' => $userIndividualPermissionsIds,
            'role_permission_ids' => $rolePermissionsIds,
        ]);
    }

    public function syncPermissions(Request $request, User $usuario)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:core.permissions,id_permision',
        ]);

        $usuario->individualPermissions()->syncWithPivotValues($request->permissions, ['model_type' => User::class]);

        return response()->json([
            'success' => true,
            'message' => 'Permisos individuales actualizados correctamente.',
        ]);
    }

    public function importLegacyUsers()
    {
        return response()->json([
            'success' => false,
            'message' => 'La importación local ya no aplica. Los usuarios se centralizan en el SSO/SIGETH.'
        ], 422);
    }
}


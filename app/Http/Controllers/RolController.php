<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use Illuminate\Http\Request;

class RolController extends Controller
{
    public function index()
    {
        return Rol::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        return Rol::create([
            'name' => $validated['nombre'],
            'description' => $validated['descripcion'],
            'activo' => $validated['activo'] ?? true,
            'guard_name' => 'web'
        ]);
    }

    public function update(Request $request, Rol $rol)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $rol->update([
            'name' => $validated['nombre'],
            'description' => $validated['descripcion'],
            'activo' => $validated['activo'] ?? $rol->activo
        ]);
        return $rol;
    }

    public function destroy(Rol $rol)
    {
        $rol->delete();
        return response()->noContent();
    }

    /**
     * 🆔 GET ROLE PERMISSIONS
     */
    public function getPermissions(Rol $rol)
    {
        $allPermissions = \App\Models\Permission::with('systems')->get();
        $rolePermissionsIds = $rol->permissions()->pluck('permission_id')->toArray();

        return response()->json([
            'all_permissions' => $allPermissions,
            'role_permission_ids' => $rolePermissionsIds,
        ]);
    }

    /**
     * 🆔 SYNC ROLE PERMISSIONS
     */
    public function syncPermissions(Request $request, Rol $rol)
    {
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:core.permissions,id',
        ]);

        $rol->permissions()->sync($request->permissions);

        return response()->json([
            'success' => true,
            'message' => 'Permisos del rol actualizados correctamente.',
        ]);
    }
}

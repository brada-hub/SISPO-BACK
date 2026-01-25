<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        return User::with('rol')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rol_id' => 'required|exists:roles,id',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'ci' => 'required|string|unique:users,ci',
            'activo' => 'boolean',
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
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'ci' => 'required|string|unique:users,ci,' . $usuario->id,
            'activo' => 'boolean',
        ]);

        $usuario->update($validated);
        return $usuario;
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
        $usuario->delete();
        return response()->noContent();
    }
}

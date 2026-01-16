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
            'nombre' => 'required|string|max:255|unique:roles,nombre',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        return Rol::create($validated);
    }

    public function update(Request $request, Rol $rol)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:roles,nombre,' . $rol->id,
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $rol->update($validated);
        return $rol;
    }

    public function destroy(Rol $rol)
    {
        $rol->delete();
        return response()->noContent();
    }
}
